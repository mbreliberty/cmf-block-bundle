<?php

namespace Symfony\Cmf\Bundle\BlockBundle\Cache;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\BlockBundle\Block\BlockRendererInterface;
use Sonata\BlockBundle\Block\BlockLoaderInterface;
use Sonata\CacheBundle\Cache\CacheInterface;
use Sonata\CacheBundle\Cache\CacheElement;

/**
 * Cache a block through Javascript code
 */
class BlockJsCache implements CacheInterface
{
    protected $router;
    protected $sync;
    protected $blockRenderer;
    protected $blockLoader;

    /**
     * @param \Symfony\Component\Routing\RouterInterface $router
     * @param \Sonata\BlockBundle\Block\BlockRendererInterface $blockRenderer
     * @param bool $sync
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param $documentManagerName
     */
    public function __construct(RouterInterface $router, BlockRendererInterface $blockRenderer, $sync = false, BlockLoaderInterface $blockLoader)
    {
        $this->router        = $router;
        $this->sync          = $sync;
        $this->blockRenderer = $blockRenderer;
        $this->blockLoader   = $blockLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function flushAll()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(array $keys = array())
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(array $keys)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(array $keys)
    {
        $this->validateKeys($keys);

        return new CacheElement($keys, new Response($this->sync ? $this->getSync($keys) : $this->getAsync($keys)));
    }

    /**
     * @throws \RuntimeException
     *
     * @param array $keys
     *
     * @return void
     */
    private function validateKeys(array $keys)
    {
        foreach (array('block_id', 'updated_at') as $key) {
            if (!isset($keys[$key])) {
                throw new \RuntimeException(sprintf('Please define a `%s` key', $key));
            }
        }
    }

    /**
     * @param array $keys
     *
     * @return string
     */
    protected function getSync(array $keys)
    {
        $dashifiedId = $this->dashify($keys['block_id']);

        return sprintf(<<<CONTENT
<div id="block%s" >
    <script type="text/javascript">
        /*<![CDATA[*/
            (function () {
                var block, xhr, node, parentNode, replace;
                block = document.getElementById('block%s');
                parentNode = block.parentNode;
                if (window.XMLHttpRequest) {
                    xhr = new XMLHttpRequest();
                } else {
                    xhr = new ActiveXObject('Microsoft.XMLHTTP');
                }

                xhr.open('GET', '%s', false);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send('');

                // create an empty element
                var div = document.createElement("div");
                div.innerHTML = xhr.responseText;

                replace = true;
                for (var node in div.childNodes) {
                    if (div.childNodes[node] && div.childNodes[node].nodeType == 1) {
                        if (replace) {
                            parentNode.replaceChild(div.childNodes[node], block);
                            replace = false;
                        } else {
                            parentNode.appendChild(div.childNodes[node]);
                        }
                    }
                }
            })();
        /*]]>*/
    </script>
</div>
CONTENT
, $dashifiedId, $dashifiedId, $this->router->generate('symfony_cmf_block_js_sync_cache', $keys, true));
    }

    /**
     * @param array $keys
     * @return string
     */
    protected function getAsync(array $keys)
    {
        return sprintf(<<<CONTENT
<div id="block%s" >
    <script type="text/javascript">
        /*<![CDATA[*/

            (function() {
                var b = document.createElement('script');
                b.type = 'text/javascript';
                b.async = true;
                b.src = '%s'
                var s = document.getElementsByTagName('script')[0];
                s.parentNode.insertBefore(b, s);
            })();

        /*]]>*/
    </script>
</div>
CONTENT
, $this->dashify($keys['block_id']), $this->router->generate('symfony_cmf_block_js_async_cache', $keys, true));
    }

    /**
     * {@inheritdoc}
     */
    public function set(array $keys, $data, $ttl = 84600, array $contextualKeys = array())
    {
        $this->validateKeys($keys);

        return new CacheElement($keys, $data, $ttl, $contextualKeys);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function cacheAction(Request $request)
    {
        $block = $this->blockLoader->load(array('name' => $request->get('block_id')));

        if (!$block) {
            return new Response('', 404);
        }

        $response = $this->blockRenderer->render($block);
        $response->setPrivate(); //  always set to private

        if ($this->sync) {
            return $response;
        }

        $response->setContent(sprintf(<<<JS
    (function () {
        var block = document.getElementById('block%s'),
            div = document.createElement("div"),
            parentNode = block.parentNode,
            node,
            replace = true;

        div.innerHTML = %s;

        for (var node in div.childNodes) {
            if (div.childNodes[node] && div.childNodes[node].nodeType == 1) {
                if (replace) {
                    parentNode.replaceChild(div.childNodes[node], block);
                    replace = false;
                } else {
                    parentNode.appendChild(div.childNodes[node]);
                }
            }
        }
    })();
JS
, $block->getDashifiedId(), json_encode($response->getContent())));

        $response->headers->set('Content-Type', 'application/javascript');

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function isContextual()
    {
        return false;
    }

    /**
     * @param string $src
     */
    protected function dashify($src)
    {
        return preg_replace('/[\/\.]/', '-', $src);
    }
}