#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

php -v | tee /tmp/php-version.txt
php -r '
$v = PHP_VERSION;
if (version_compare($v, "8.2.0", "<")) {
    fwrite(STDERR, "PHP {$v} is older than required 8.2.0\n");
    exit(1);
}
'

for ext in dom json libxml mbstring tokenizer xml xmlwriter; do
    if ! php -m | grep -qi "^${ext}$"; then
        echo "Missing required PHP extension: ${ext}" >&2
        exit 1
    fi
done

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256} /tmp/composer.phar" | sha256sum -c -

composer_out="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
if ! grep -E '^Generating autoload files$' "${composer_out}" >/dev/null; then
    echo 'composer update: missing exact success line' >&2
    exit 1
fi
rm -f "${composer_out}"

pin_out="$(mktemp)"
set +e
php scripts/check-cluster-pins.php >"${pin_out}" 2>&1
pin_status=$?
set -e
cat "${pin_out}"
fail_deprecation_output "check-cluster-pins" "${pin_out}"
if [[ ${pin_status} -ne 0 ]]; then
    rm -f "${pin_out}"
    exit "${pin_status}"
fi
if ! grep -Fxq 'check-cluster-pins: all cluster packages match expected SHAs and aliases.' "${pin_out}"; then
    echo 'check-cluster-pins: missing success line' >&2
    exit 1
fi
if ! grep -Fxq 'check-cluster-pins: wrong-SHA demo installed.php failed as expected.' "${pin_out}"; then
    echo 'check-cluster-pins: missing installed.php demo line' >&2
    exit 1
fi
if ! grep -Fxq 'check-cluster-pins: wrong-SHA demo installed.json failed as expected.' "${pin_out}"; then
    echo 'check-cluster-pins: missing installed.json demo line' >&2
    exit 1
fi
rm -f "${pin_out}"

rename_out="$(mktemp)"
set +e
php scripts/check-rename.php >"${rename_out}" 2>&1
rename_status=$?
set -e
cat "${rename_out}"
fail_deprecation_output "check-rename" "${rename_out}"
if [[ ${rename_status} -ne 0 ]]; then
    rm -f "${rename_out}"
    exit "${rename_status}"
fi
if ! grep -Fxq 'check-rename: no old or camelCase repo-owned names remain.' "${rename_out}"; then
    echo 'check-rename: missing rename success line' >&2
    exit 1
fi
if ! grep -Fxq 'check-baseline: 341 tests, 341 assertions, 43 spec groups.' "${rename_out}"; then
    echo 'check-rename: missing baseline success line' >&2
    exit 1
fi
if ! grep -Fxq 'check-background-position: integer-percent branch OK (spec: flip background-position percentages, 0% -> 100%).' "${rename_out}"; then
    echo 'check-rename: missing background-position demo line' >&2
    exit 1
fi
rm -f "${rename_out}"

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit --abandoned=report >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
    echo 'composer audit: missing exact success line' >&2
    exit 1
fi
rm -f "${audit_out}"

lint_out="$(mktemp)"
set +e
vendor/bin/parallel-lint . --exclude vendor >"${lint_out}" 2>&1
lint_status=$?
set -e
cat "${lint_out}"
fail_deprecation_output "parallel-lint" "${lint_out}"
if [[ ${lint_status} -ne 0 ]]; then
    exit "${lint_status}"
fi
if ! grep -Fxq 'No syntax error found' "${lint_out}"; then
    echo 'parallel-lint: missing exact success line' >&2
    exit 1
fi
rm -f "${lint_out}"

phpunit_out="$(mktemp)"
set +e
vendor/bin/phpunit --colors=never --no-coverage >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
cat "${phpunit_out}"
fail_deprecation_output "phpunit" "${phpunit_out}"
if [[ ${phpunit_status} -ne 0 ]]; then
    exit "${phpunit_status}"
fi
if ! grep -E '^OK \(341 tests, 341 assertions\)$' "${phpunit_out}" >/dev/null; then
    echo 'phpunit: missing strict OK success line' >&2
    exit 1
fi
rm -f "${phpunit_out}"

phpcs_out="$(mktemp)"
phpcs_stripped="$(mktemp)"
set +e
vendor/bin/phpcs --report=json --no-colors . >"${phpcs_out}" 2>&1
phpcs_status=$?
set -e
cat "${phpcs_out}"
fail_deprecation_output "phpcs" "${phpcs_out}"
if [[ ${phpcs_status} -ne 0 ]]; then
    rm -f "${phpcs_out}" "${phpcs_stripped}"
    exit "${phpcs_status}"
fi
sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${phpcs_out}" >"${phpcs_stripped}"
phpcs_gate="$(mktemp)"
set +e
php -r '
$path = $argv[1];
$raw = file_get_contents($path);
if ($raw === false) {
    fwrite(STDERR, "phpcs gate: could not read report\n");
    exit(1);
}
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data["totals"])) {
    fwrite(STDERR, "phpcs gate: invalid JSON report\n");
    exit(1);
}
$errors = (int)($data["totals"]["errors"] ?? -1);
$warnings = (int)($data["totals"]["warnings"] ?? -1);
echo "phpcs: {$errors} errors, {$warnings} warnings\n";
if ($errors !== 0 || $warnings !== 0) {
    exit(1);
}
' "${phpcs_stripped}" | tee "${phpcs_gate}"
phpcs_gate_status=${PIPESTATUS[0]}
set -e
if [[ ${phpcs_gate_status} -ne 0 ]]; then
    rm -f "${phpcs_out}" "${phpcs_stripped}" "${phpcs_gate}"
    exit "${phpcs_gate_status}"
fi
if ! grep -E '^phpcs: 0 errors, 0 warnings$' "${phpcs_gate}" >/dev/null; then
    echo 'phpcs: missing anchored success line' >&2
    rm -f "${phpcs_out}" "${phpcs_stripped}" "${phpcs_gate}"
    exit 1
fi
rm -f "${phpcs_out}" "${phpcs_stripped}" "${phpcs_gate}"

phan_out="$(mktemp)"
set +e
vendor/bin/phan --allow-polyfill-parser --no-color --output-mode=json >"${phan_out}" 2>&1
phan_status=$?
set -e
cat "${phan_out}"
fail_deprecation_output "phan" "${phan_out}"
if [[ ${phan_status} -ne 0 ]]; then
    exit "${phan_status}"
fi
if ! grep -Fxq '[]' "${phan_out}"; then
    echo 'phan: expected empty JSON array success output' >&2
    exit 1
fi
rm -f "${phan_out}"

echo 'system-test: all steps passed.'
