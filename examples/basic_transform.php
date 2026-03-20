<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// --- Example 1: Basic LTR to RTL transformation ---
$ltr_css = '.sidebar { float: left; margin-right: 10px; padding-left: 5px; }';
$rtl_css = Css_Janus::transform($ltr_css);
echo $rtl_css;
// .sidebar { float: right; margin-left: 10px; padding-right: 5px; }
echo "\n\n";

// --- Example 2: Four-part shorthand notation ---
$ltr_padding = '.box { padding: 10px 20px 30px 40px; }';
echo Css_Janus::transform($ltr_padding);
// .box { padding: 10px 40px 30px 20px; }
echo "\n\n";

// --- Example 3: Direction property ---
$ltr_dir = 'body { direction: ltr; }';
echo Css_Janus::transform($ltr_dir);
// body { direction: rtl; }
echo "\n\n";

// --- Example 4: Protect rules from transformation with @noflip ---
$mixed = '.logo { background-position: left top; } /* @noflip */ .icon { float: left; }';
echo Css_Janus::transform($mixed);
// .logo { background-position: right top; } /* @noflip */ .icon { float: left; }
echo "\n\n";

// --- Example 5: Transform directions inside URLs ---
$url_css = '.rtl-bg { background: url("images/ltr-arrow.png"); }';
echo Css_Janus::transform($url_css, ['transformDirInUrl' => true]);
// .rtl-bg { background: url("images/rtl-arrow.png"); }
echo "\n\n";

// --- Example 6: border-radius corner swapping ---
$radius = '.card { border-radius: 5px 10px 15px 20px; }';
echo Css_Janus::transform($radius);
// .card { border-radius: 10px 5px 20px 15px; }
echo "\n";
