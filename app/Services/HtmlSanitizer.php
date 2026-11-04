<?php

namespace App\Services;

class HtmlSanitizer
{
    public function clean(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,h2,h3,ul,ol,li,a[href],blockquote');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('Cache.SerializerPath', storage_path('framework/cache/purifier'));

        if (! is_dir(storage_path('framework/cache/purifier'))) {
            mkdir(storage_path('framework/cache/purifier'), 0755, true);
        }

        return (new \HTMLPurifier($config))->purify($html);
    }
}
