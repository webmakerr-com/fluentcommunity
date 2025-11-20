<?php

namespace FluentCommunity\App\Services;

class EmailDomBuilder
{
    protected $config = [];

    protected $elements = [];

    public function __construct($config = [])
    {
        $defaults = [
            'font_size'   => '14px',
            'font_family' => 'Arial, Helvetica, sans-serif',
        ];

        $this->config = $config;
    }

    public function line($content, $tag = 'p')
    {
        $this->elements[] = [
            'type'    => 'line',
            'tag'     => $tag,
            'content' => $content,
        ];
        return $this;
    }

    public function heading($content, $tag = 'h2')
    {
        $this->elements[] = [
            'type'    => 'heading',
            'tag'     => $tag,
            'content' => $content,
        ];
        return $this;
    }

    public function button($url, $btnText, $style = 'primary')
    {
        $this->elements[] = [
            'type'    => 'button',
            'content' => $btnText,
            'url'     => $url,
            'style'   => $style
        ];
        return $this;
    }

    public function url($url, $content)
    {
        $this->elements[] = [
            'type'    => 'url',
            'content' => $content,
            'url'     => $url,
        ];
        return $this;
    }

    public function image($imageUrl, $alt = '')
    {
        $this->elements[] = [
            'type'    => 'image',
            'content' => $imageUrl,
            'alt'     => $alt,
        ];
        return $this;
    }

}
