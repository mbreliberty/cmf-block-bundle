<?php

/*
 * This file is part of the Symfony CMF package.
 *
 * (c) 2011-2017 Symfony CMF
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Cmf\Bundle\BlockBundle\Doctrine\Phpcr;

use Symfony\Cmf\Bundle\CoreBundle\Translatable\TranslatableInterface;

/**
 * Block that contains only text.
 */
class StringBlock extends AbstractBlock implements TranslatableInterface
{
    protected ?string $body;

    /**
     * {@inheritdoc}
     */
    public function getType(): ?string
    {
        return 'cmf.block.string';
    }

    /**
     * Set body.
     *
     * @param string $body
     *
     * @return $this
     */
    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get body.
     *
     * @return string
     */
    public function getBody(): ?string
    {
        return $this->body;
    }
}
