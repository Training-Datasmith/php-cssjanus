<?php

declare (strict_types=1);
/**
 * PHP port of CSSJanus. https://www.mediawiki.org/wiki/CSSJanus
 *
 * Copyright 2020 Timo Tijhof
 * Copyright 2012 Trevor Parscal
 * Copyright 2010 Roan Kattouw
 * Copyright 2008 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @file
 */
/**
 * CSSJanus is a utility that converts CSS stylesheets
 * from left-to-right (LTR) to right-to-left (RTL).
 */
class Css_Janus
{
    private const TOKEN_TMP = '`TMP`';
    private const TOKEN_LTR_TMP = '`TMPLTR`';
    private const TOKEN_RTL_TMP = '`TMPRTL`';
    private const TOKEN_COMMENT = '`COMMENT`';
    private static ?array $patterns = null;
    private static function build_patterns(): void
    {
        if (self::$patterns !== null) {
            return;
        }
        // Patterns defined as null are built dynamically
        $patterns = ['nonAscii' => '[\200-\377]', 'unicode' => '(?:(?:\\\\[0-9a-f]{1,6})(?:\r\n|\s)?)', 'num' => '(?:[0-9]*\.[0-9]+|[0-9]+)', 'unit' => '(?:em|ex|px|cm|mm|in|pt|pc|deg|rad|grad|ms|s|hz|khz|%)', 'body_selector' => 'body\s*{\s*', 'direction' => 'direction\s*:\s*', 'escape' => null, 'nmstart' => null, 'nmchar' => null, 'ident' => null, 'quantity' => null, 'possibly_negative_quantity' => null, 'color' => null, 'url_special_chars' => '[!#$%&*-~]', 'valid_after_uri_chars' => '[\'\"]?\s*', 'url_chars' => null, 'lookahead_not_open_brace' => null, 'lookahead_not_closing_paren' => null, 'lookahead_for_closing_paren' => null, 'lookahead_not_letter' => '(?![a-zA-Z])', 'lookbehind_not_letter' => '(?<![a-zA-Z])', 'chars_within_selector' => '[^\}]*?', 'noflip_annotation' => '\/\*\!?\s*@noflip\s*\*\/', 'noflip_single' => null, 'noflip_class' => null, 'comment' => '/\/\*[^*]*\*+([^\/*][^*]*\*+)*\//', 'direction_ltr' => null, 'direction_rtl' => null, 'left' => null, 'right' => null, 'left_in_url' => null, 'right_in_url' => null, 'ltr_dir_selector' => '/(:dir\( *)ltr( *\))/', 'rtl_dir_selector' => '/(:dir\( *)rtl( *\))/', 'ltr_in_url' => null, 'rtl_in_url' => null, 'cursor_east' => null, 'cursor_west' => null, 'four_notation_quantity' => null, 'four_notation_color' => null, 'border_radius' => null, 'box_shadow' => null, 'text_shadow1' => null, 'text_shadow2' => null, 'bg_horizontal_percentage' => null, 'bg_horizontal_percentage_x' => null, 'suffix' => '(\s*(?:!important\s*)?[;}])'];
        // phpcs:disable Generic.Files.LineLength.TooLong
        $patterns['escape'] = "(?:{$patterns['unicode']}|\\\\[^\\r\\n\\f0-9a-f])";
        $patterns['nmstart'] = "(?:[_a-z]|{$patterns['nonAscii']}|{$patterns['escape']})";
        $patterns['nmchar'] = "(?:[_a-z0-9-]|{$patterns['nonAscii']}|{$patterns['escape']})";
        $patterns['ident'] = "-?{$patterns['nmstart']}{$patterns['nmchar']}*";
        $patterns['quantity'] = "{$patterns['num']}(?:\\s*{$patterns['unit']}|{$patterns['ident']})?";
        $patterns['possibly_negative_quantity'] = "((?:-?{$patterns['quantity']})|(?:inherit|auto))";
        $patterns['possibly_negative_simple_quantity'] = "(?:-?{$patterns['num']}(?:\\s*{$patterns['unit']})?)";
        $patterns['math_operator'] = '(?:\+|\-|\*|\/)';
        $patterns['allowed_chars'] = '(?:\(|\)|\t| )';
        $patterns['calc_equation'] = "(?:{$patterns['allowed_chars']}|{$patterns['possibly_negative_simple_quantity']}|{$patterns['math_operator']}){3,}";
        $patterns['calc'] = "(?:calc\\((?:{$patterns['calc_equation']})\\))";
        $patterns['possibly_negative_quantity_calc'] = "((?:-?{$patterns['quantity']})|(?:inherit|auto)|{$patterns['calc']})";
        $patterns['color'] = "(#?{$patterns['nmchar']}+|(?:rgba?|hsla?)\\([ \\d.,%-]+\\))";
        // Use "*+" instead of "*?" to avoid reaching the backtracking limit.
        // <https://phabricator.wikimedia.org/T326481>, <https://phabricator.wikimedia.org/T215746#4944830>.
        $patterns['url_chars'] = "(?:{$patterns['url_special_chars']}|{$patterns['nonAscii']}|{$patterns['escape']})*+";
        $patterns['lookahead_not_open_brace'] = "(?!({$patterns['nmchar']}|\\r?\\n|\\s|#|\\:|\\.|\\,|\\+|>|~|\\(|\\)|\\[|\\]|=|\\*=|~=|\\^=|'[^']*'|\"[^\"]*\"|" . self::TOKEN_COMMENT . ')*+{)';
        $patterns['lookahead_not_closing_paren'] = "(?!{$patterns['url_chars']}{$patterns['valid_after_uri_chars']}\\))";
        $patterns['lookahead_for_closing_paren'] = "(?={$patterns['url_chars']}{$patterns['valid_after_uri_chars']}\\))";
        $patterns['noflip_single'] = "/({$patterns['noflip_annotation']}{$patterns['lookahead_not_open_brace']}[^;}]+;?)/i";
        $patterns['noflip_class'] = "/({$patterns['noflip_annotation']}{$patterns['chars_within_selector']}})/i";
        $patterns['direction_ltr'] = "/({$patterns['direction']})ltr/i";
        $patterns['direction_rtl'] = "/({$patterns['direction']})rtl/i";
        $patterns['left'] = "/{$patterns['lookbehind_not_letter']}(left){$patterns['lookahead_not_letter']}{$patterns['lookahead_not_closing_paren']}{$patterns['lookahead_not_open_brace']}/i";
        $patterns['right'] = "/{$patterns['lookbehind_not_letter']}(right){$patterns['lookahead_not_letter']}{$patterns['lookahead_not_closing_paren']}{$patterns['lookahead_not_open_brace']}/i";
        $patterns['left_in_url'] = "/{$patterns['lookbehind_not_letter']}(left){$patterns['lookahead_for_closing_paren']}/i";
        $patterns['right_in_url'] = "/{$patterns['lookbehind_not_letter']}(right){$patterns['lookahead_for_closing_paren']}/i";
        $patterns['ltr_in_url'] = "/{$patterns['lookbehind_not_letter']}(ltr){$patterns['lookahead_for_closing_paren']}/i";
        $patterns['rtl_in_url'] = "/{$patterns['lookbehind_not_letter']}(rtl){$patterns['lookahead_for_closing_paren']}/i";
        $patterns['cursor_east'] = "/{$patterns['lookbehind_not_letter']}([ns]?)e-resize/";
        $patterns['cursor_west'] = "/{$patterns['lookbehind_not_letter']}([ns]?)w-resize/";
        $patterns['four_notation_quantity_props'] = "((?:margin|padding|border-width)\\s*:\\s*)";
        $patterns['four_notation_quantity'] = "/{$patterns['four_notation_quantity_props']}{$patterns['possibly_negative_quantity_calc']}(\\s+){$patterns['possibly_negative_quantity_calc']}(\\s+){$patterns['possibly_negative_quantity_calc']}(\\s+){$patterns['possibly_negative_quantity_calc']}{$patterns['suffix']}/i";
        $patterns['four_notation_color'] = "/((?:-color|border-style)\\s*:\\s*){$patterns['color']}(\\s+){$patterns['color']}(\\s+){$patterns['color']}(\\s+){$patterns['color']}{$patterns['suffix']}/i";
        // border-radius: <length or percentage>{1,4} [optional: / <length or percentage>{1,4} ]
        $patterns['border_radius'] = '/(border-radius\s*:\s*)' . $patterns['possibly_negative_quantity'] . '(?:(?:\s+' . $patterns['possibly_negative_quantity'] . ')(?:\s+' . $patterns['possibly_negative_quantity'] . ')?(?:\s+' . $patterns['possibly_negative_quantity'] . ')?)?' . '(?:(?:(?:\s*\/\s*)' . $patterns['possibly_negative_quantity'] . ')(?:\s+' . $patterns['possibly_negative_quantity'] . ')?(?:\s+' . $patterns['possibly_negative_quantity'] . ')?(?:\s+' . $patterns['possibly_negative_quantity'] . ')?)?' . $patterns['suffix'] . '/i';
        $patterns['box_shadow'] = "/(box-shadow\\s*:\\s*(?:inset\\s*)?){$patterns['possibly_negative_quantity']}/i";
        $patterns['text_shadow1'] = "/(text-shadow\\s*:\\s*){$patterns['possibly_negative_quantity']}(\\s*){$patterns['color']}/i";
        $patterns['text_shadow2'] = "/(text-shadow\\s*:\\s*){$patterns['color']}(\\s*){$patterns['possibly_negative_quantity']}/i";
        $patterns['text_shadow3'] = "/(text-shadow\\s*:\\s*){$patterns['possibly_negative_quantity']}/i";
        $patterns['bg_horizontal_percentage'] = "/(background(?:-position)?\\s*:\\s*(?:[^:;}\\s]+\\s+)*?)({$patterns['quantity']})/i";
        $patterns['bg_horizontal_percentage_x'] = "/(background-position-x\\s*:\\s*)(-?{$patterns['num']}%)/i";
        $patterns['translate_x'] = "/(transform\\s*:[^;}]*)(translateX\\s*\\(\\s*){$patterns['possibly_negative_quantity']}(\\s*\\))/i";
        $patterns['translate'] = "/(transform\\s*:[^;}]*)(translate\\s*\\(\\s*){$patterns['possibly_negative_quantity']}((?:\\s*,\\s*{$patterns['possibly_negative_quantity']}){0,2}\\s*\\))/i";
        // phpcs:enable
        self::$patterns = $patterns;
    }
    /**
     * Transform an LTR stylesheet to RTL
     *
     * @param string $css Stylesheet to transform
     * @param bool|array{transformDirInUrl?:bool,transformEdgeInUrl?:bool} $options Options array,
     * or value of transformDirInUrl option (back-compat)
     *  - transformDirInUrl: Transform directions in URLs (ltr/rtl). Default: false.
     *  - transformEdgeInUrl: Transform edges in URLs (left/right). Default: false.
     * @param bool $transformEdgeInUrl [optional] For back-compat
     * @return string Transformed stylesheet
     */
    public static function transform($css, $options = [], $transform_edge_in_url = false)
    {
        if (!is_array($options)) {
            $options = ['transformDirInUrl' => (bool) $options, 'transformEdgeInUrl' => (bool) $transform_edge_in_url];
        }
        // Defaults
        $options += ['transformDirInUrl' => false, 'transformEdgeInUrl' => false];
        self::build_patterns();
        // We wrap tokens in ` , not ~ like the original implementation does.
        // This was done because ` is not a legal character in CSS and can only
        // occur in URLs, where we escape it to %60 before inserting our tokens.
        $css = str_replace('`', '%60', $css);
        // Tokenize single line rules with /* @noflip */
        $no_flip_single = new Css_Janus_Tokenizer(self::$patterns['noflip_single'], '`NOFLIP_SINGLE`');
        $css = $no_flip_single->tokenize($css);
        // Tokenize class rules with /* @noflip */
        $no_flip_class = new Css_Janus_Tokenizer(self::$patterns['noflip_class'], '`NOFLIP_CLASS`');
        $css = $no_flip_class->tokenize($css);
        // Tokenize comments
        $comments = new Css_Janus_Tokenizer(self::$patterns['comment'], self::TOKEN_COMMENT);
        $css = $comments->tokenize($css);
        // LTR->RTL fixes start here
        $css = self::fix_direction($css);
        if ($options['transformDirInUrl']) {
            $css = self::fix_ltr_rtl_in_url($css);
        }
        if ($options['transformEdgeInUrl']) {
            $css = self::fix_left_right_in_url($css);
        }
        $css = self::fix_left_and_right($css);
        $css = self::fix_cursor_properties($css);
        $css = self::fix_four_part_notation($css);
        $css = self::fix_border_radius($css);
        $css = self::fix_background_position($css);
        $css = self::fix_shadows($css);
        $css = self::fix_translate($css);
        // Detokenize stuff we tokenized before
        $css = $comments->detokenize($css);
        $css = $no_flip_class->detokenize($css);
        return $no_flip_single->detokenize($css);
    }
    /**
     * Replace direction: ltr; with direction: rtl; and vice versa.
     *
     * The original implementation only does this inside body selectors
     * and misses "body\n{\ndirection:ltr;\n}". This function does not have
     * these problems.
     *
     * See https://code.google.com/p/cssjanus/issues/detail?id=15
     *
     * @param string $css
     */
    private static function fix_direction($css): string
    {
        $css = preg_replace(self::$patterns['direction_ltr'], '$1' . self::TOKEN_TMP, $css);
        $css = preg_replace(self::$patterns['direction_rtl'], '$1ltr', $css);
        return str_replace(self::TOKEN_TMP, 'rtl', $css);
    }
    /**
     * Replace 'ltr' with 'rtl' and vice versa in background URLs
     * @param string $css
     */
    private static function fix_ltr_rtl_in_url($css): string
    {
        $css = preg_replace(self::$patterns['ltr_dir_selector'], '$1' . self::TOKEN_LTR_TMP . '$2', $css);
        $css = preg_replace(self::$patterns['rtl_dir_selector'], '$1' . self::TOKEN_RTL_TMP . '$2', $css);
        $css = preg_replace(self::$patterns['ltr_in_url'], self::TOKEN_TMP, $css);
        $css = preg_replace(self::$patterns['rtl_in_url'], 'ltr', $css);
        $css = str_replace(self::TOKEN_TMP, 'rtl', $css);
        $css = str_replace(self::TOKEN_LTR_TMP, 'ltr', $css);
        return str_replace(self::TOKEN_RTL_TMP, 'rtl', $css);
    }
    /**
     * Replace 'left' with 'right' and vice versa in background URLs
     * @param string $css
     */
    private static function fix_left_right_in_url($css): string
    {
        $css = preg_replace(self::$patterns['left_in_url'], self::TOKEN_TMP, $css);
        $css = preg_replace(self::$patterns['right_in_url'], 'left', $css);
        return str_replace(self::TOKEN_TMP, 'right', $css);
    }
    /**
     * Flip rules like left: , padding-right: , etc.
     * @param string $css
     */
    private static function fix_left_and_right($css): string
    {
        $css = preg_replace(self::$patterns['left'], self::TOKEN_TMP, $css);
        $css = preg_replace(self::$patterns['right'], 'left', $css);
        return str_replace(self::TOKEN_TMP, 'right', $css);
    }
    /**
     * Flip East and West in rules like cursor: nw-resize;
     * @param string $css
     */
    private static function fix_cursor_properties($css): string
    {
        $css = preg_replace(self::$patterns['cursor_east'], '$1' . self::TOKEN_TMP, $css);
        $css = preg_replace(self::$patterns['cursor_west'], '$1e-resize', $css);
        return str_replace(self::TOKEN_TMP, 'w-resize', $css);
    }
    /**
     * Swap the second and fourth parts in four-part notation rules like
     * padding: 1px 2px 3px 4px;
     *
     * Unlike the original implementation, this function doesn't suffer from
     * the bug where whitespace is not preserved when flipping four-part rules
     * and four-part color rules with multiple whitespace characters between
     * colors are not recognized.
     * See https://code.google.com/p/cssjanus/issues/detail?id=16
     * @param string $css
     * @return string
     */
    private static function fix_four_part_notation($css): ?string
    {
        $css = preg_replace(self::$patterns['four_notation_quantity'], '$1$2$3$8$5$6$7$4$9', $css);
        return preg_replace(self::$patterns['four_notation_color'], '$1$2$3$8$5$6$7$4$9', $css);
    }
    /**
     * Swaps appropriate corners in border-radius values.
     *
     * @param string $css
     * @return string
     */
    private static function fix_border_radius($css): ?string
    {
        return preg_replace_callback(self::$patterns['border_radius'], static function ($matches): string {
            $pre = $matches[1];
            $first_group = array_filter(array_slice($matches, 2, 4), 'strlen');
            $second_group = array_filter(array_slice($matches, 6, 4), 'strlen');
            $post = $matches[10] ?: '';
            if ($second_group) {
                $values = self::flip_border_radius_values($first_group) . ' / ' . self::flip_border_radius_values($second_group);
            } else {
                $values = self::flip_border_radius_values($first_group);
            }
            return $pre . $values . $post;
        }, $css);
    }
    /**
     * Callback for fixBorderRadius()
     * @param array $values Matched values
     * @return string Flipped values
     */
    private static function flip_border_radius_values(array $values): string
    {
        switch (count($values)) {
            case 4:
                $values = [$values[1], $values[0], $values[3], $values[2]];
                break;
            case 3:
                $values = [$values[1], $values[0], $values[1], $values[2]];
                break;
            case 2:
                $values = [$values[1], $values[0]];
                break;
            case 1:
                $values = [$values[0]];
                break;
        }
        return implode(' ', $values);
    }
    /**
     * Flips the sign of a CSS value, possibly with a unit.
     *
     * We can't just negate the value with unary minus due to the units.
     *
     * @return string
     */
    private static function flip_sign(string $css_value)
    {
        // Don't mangle zeroes
        if (floatval($css_value) === 0.0) {
            return $css_value;
        }
        // Don't mangle zeroes
        if ($css_value[0] === '-') {
            return substr($css_value, 1);
        }
        return '-' . $css_value;
    }
    /**
     * Negates horizontal offset in box-shadow and text-shadow rules.
     *
     * @param string $css
     * @return string
     */
    private static function fix_shadows($css): ?string
    {
        $css = preg_replace_callback(self::$patterns['box_shadow'], fn($matches) => $matches[1] . self::flip_sign($matches[2]), $css);
        $css = preg_replace_callback(self::$patterns['text_shadow1'], fn($matches) => $matches[1] . $matches[2] . $matches[3] . self::flip_sign($matches[4]), $css);
        $css = preg_replace_callback(self::$patterns['text_shadow2'], fn($matches) => $matches[1] . $matches[2] . $matches[3] . self::flip_sign($matches[4]), $css);
        return preg_replace_callback(self::$patterns['text_shadow3'], fn($matches) => $matches[1] . self::flip_sign($matches[2]), $css);
    }
    /**
     * Negates horizontal offset in tranform: translate()
     *
     * @param string $css
     * @return string
     */
    private static function fix_translate($css): ?string
    {
        $css = preg_replace_callback(self::$patterns['translate'], fn($matches) => $matches[1] . $matches[2] . self::flip_sign($matches[3]) . $matches[4], $css);
        return preg_replace_callback(self::$patterns['translate_x'], fn($matches) => $matches[1] . $matches[2] . self::flip_sign($matches[3]) . $matches[4], $css);
    }
    /**
     * Flip horizontal background percentages.
     * @param string $css
     * @return string
     */
    private static function fix_background_position($css)
    {
        $callback = static function ($matches): string {
            $value = $matches[2];
            if (substr($value, -1) === '%') {
                $idx = strpos($value, '.');
                if ($idx !== false) {
                    $len = strlen($value) - $idx - 2;
                    $value = number_format(100 - (float) $value, $len) . '%';
                } else {
                    $value = 100 - (float) $value . '%';
                }
            }
            return $matches[1] . $value;
        };
        $replaced = preg_replace_callback(self::$patterns['bg_horizontal_percentage'], $callback, $css);
        if ($replaced !== null) {
            // preg_replace_callback() sometimes returns null
            $css = $replaced;
        }
        $replaced = preg_replace_callback(self::$patterns['bg_horizontal_percentage_x'], $callback, $css);
        if ($replaced !== null) {
            return $replaced;
        }
        return $css;
    }
}
/**
 * Utility class used by CSSJanus that tokenizes and untokenizes things we want
 * to protect from being janused.
 */
class Css_Janus_Tokenizer
{
    private $regex;
    private $token;
    private array $originals;
    /**
     * Constructor
     * @param string $regex Regular expression whose matches to replace by a token.
     * @param string $token Token
     */
    public function __construct($regex, $token)
    {
        $this->regex = $regex;
        $this->token = $token;
        $this->originals = [];
    }
    /**
     * Replace all occurrences of $regex in $str with a token and remember
     * the original strings.
     * @param string $str to tokenize
     * @return string Tokenized string
     */
    public function tokenize($str): ?string
    {
        return preg_replace_callback($this->regex, function (array $matches) {
            $this->originals[] = $matches[0];
            return $this->token;
        }, $str);
    }
    /**
     * Replace tokens with their originals. If multiple strings were tokenized, it's important they be
     * detokenized in exactly the SAME ORDER.
     * @param string $str previously run through tokenize()
     * @return string Original string
     */
    public function detokenize($str): ?string
    {
        // PHP has no function to replace only the first occurrence or to
        // replace occurrences of the same string with different values,
        // so we use preg_replace_callback() even though we don't really need a regex
        return preg_replace_callback('/' . preg_quote($this->token, '/') . '/', function ($matches) {
            $retval = current($this->originals);
            next($this->originals);
            return $retval;
        }, $str);
    }
}