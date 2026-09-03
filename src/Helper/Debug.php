<?php

namespace App\Helper;

class Debug
{
    public function dump(mixed $data, bool $die = true): void
    {
        echo '<pre style="background:#111;color:#0f0;padding:12px;border-radius:6px;font-size:13px;">';
        print_r($data);
        echo '</pre>';

        if ($die) {
            die;
        }
    }

    public function __invoke(mixed $data, bool $die = true): void
    {
        $this->dump($data, $die);
    }
}
