<?php

namespace Solic;

use Jenssegers\Blade\Blade;

class View
{
    protected Blade $blade;
    public function __construct()
    {
        $this->blade = new Blade(__DIR__.'/views', __DIR__.'/cache');
    }

    public function render(string|object $data, string $action, string $content = '', bool $escapeContent = true): string
    {
        if (is_string($data)) {
            $data = json_decode($data);
        }

        return $this->blade->render('form', ['data' => $data->data[0], 'action' => $action, 'content' => $content, 'escapeContent' => $escapeContent]);
    }
}