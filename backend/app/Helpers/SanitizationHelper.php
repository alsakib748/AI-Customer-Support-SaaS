<?php
// app/Helpers/SanitizationHelper.php

namespace App\Helpers;

use HTMLPurifier;
use HTMLPurifier_Config;

class SanitizationHelper
{
    public static function sanitizeHtml(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'p,b,i,u,strong,em,ul,ol,li,blockquote,code,pre,h1,h2,h3,h4,h5,h6,a[href|title],img[src|alt],table,tr,td,th,thead,tbody,span,br,hr');
        $config->set('HTML.AllowedAttributes', 'a.href,a.title,img.src,img.alt,span.style');

        $purifier = new HTMLPurifier($config);
        return $purifier->purify($html);
    }
}
