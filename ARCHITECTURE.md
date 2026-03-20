# Architecture: php-cssjanus

## Purpose

A PHP port of Google's CSSJanus tool. Transforms CSS stylesheets from left-to-right (LTR) layout to right-to-left (RTL) layout. Used by MediaWiki and other projects to serve RTL-language users without maintaining duplicate stylesheets.

## Directory Structure

```
src/
  CSS_Janus.php   — Contains Css_Janus (transformer) and Css_Janus_Tokenizer (tokenizer helper)
tests/
  CSS_Janus_Test.php
```

## Key Design Decisions

- **Static class**: `Css_Janus::transform()` is a static method — no instantiation needed
- **Lazy pattern compilation**: Regex patterns are built once (on first call) and cached in `self::$patterns`; this avoids re-compiling 50+ patterns on every call
- **Tokenize-before-transform**: Rules protected by `/* @noflip */` and CSS comments are replaced with tokens before transformation, then restored afterwards, preventing false matches inside protected blocks
- **Backtick tokens**: Tokens use backticks (`` ` ``) which are illegal in CSS (except inside URLs, where they're escaped to `%60` first)
- **No dependencies**: Pure PHP, no external packages

## Extension Points

- Pass `transformDirInUrl: true` or `transformEdgeInUrl: true` in the options array to also flip `ltr`/`rtl` or `left`/`right` inside `url()` values
- Annotate CSS rules with `/* @noflip */` to exclude them from transformation

## Dependency Flow

```
Css_Janus::transform(string $css, array $options): string
  └── Css_Janus_Tokenizer  — tokenizes/detokenizes protected regions
```

## Transform Pipeline

```
Input CSS
  → escape backticks
  → tokenize @noflip single-line rules
  → tokenize @noflip class rules
  → tokenize comments
  → fix direction: ltr ↔ rtl
  → [optional] fix ltr/rtl in URLs
  → [optional] fix left/right in URLs
  → fix left ↔ right properties
  → fix cursor east ↔ west
  → fix four-part notation (top right bottom left → top left bottom right)
  → fix border-radius corners
  → fix background position percentages
  → fix shadows horizontal offset
  → fix transform translate X
  → detokenize (restore protected regions and comments)
  → Output RTL CSS
```
