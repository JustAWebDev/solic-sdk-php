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

    public function render(string $data, string $action): string
    {
        $data = json_decode($data);
        return $this->blade->render('form', ['data' => $data->data[0], 'action' => $action]);
    }
}