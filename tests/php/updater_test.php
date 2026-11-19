<?php
/**
 * Updater unit tests — no network, no database.
 *   php tests/php/updater_test.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$failures = 0;
function check(string $label, bool $cond): void
{
    global $failures;
    if ($cond) {
        echo "ok   {$label}\n";
    } else {
        echo "FAIL {$label}\n";
        $failures++;
    }
}
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

// ---------------------------------------------------------------- pure rules
check('normalize v1.2.3', Updater::normalizeVersion('v1.2.3') === '1.2.3');
check('normalize V1.2.3', Updater::normalizeVersion('V1.2.3') === '1.2.3');
check('protected: app/config.php', Updater::isProtectedPath('app/config.php'));
check('protected: .env', Updater::isProtectedPath('.env'));
check('protected: dags/x.py', Updater::isProtectedPath('dags/x.py'));
check('protected: jobs/x.py', Updater::isProtectedPath('jobs/x.py'));
check('protected: logs/runs/a.log', Updater::isProtectedPath('logs/runs/a.log'));
check('protected: storage/imports/a.py', Updater::isProtectedPath('storage/imports/a.py'));
check('protected: .git/HEAD', Updater::isProtectedPath('.git/HEAD'));
check('editable: public/index.php', !Updater::isProtectedPath('public/index.php'));
check('editable: app/bootstrap.php', !Updater::isProtectedPath('app/bootstrap.php'));
check('editable: docker/Dockerfile', !Updater::isProtectedPath('docker/Dockerfile'));

if (!class_exists('ZipArchive')) {
    echo "\nSKIP archive apply: the zip extension is not available\n";
    echo $failures === 0 ? "\nall checks passed\n" : "\n{$failures} failure(s)\n";
    exit($failures === 0 ? 0 : 1);
}

// ------------------------------------------------------------ archive apply
$tmp = sys_get_temp_dir() . '/pn_upd_' . bin2hex(random_bytes(4));
$src = $tmp . '/src/pipnix-main';
$base = $tmp . '/base';
$work = $tmp . '/work';
$archive = $tmp . '/release.zip';

$files = [
    'app/bootstrap.php' => "<?php\ndefine('PN_VERSION', '9.9.9');\n",
    'app/config.php' => "<?php return ['db' => 'FROM-ARCHIVE'];\n",
    'dags/user_dag.py' => "DAG = {}\n",
    'jobs/helper.py' => "print('x')\n",
    'logs/old.log' => "should not arrive\n",
    'storage/cache.txt' => "should not arrive\n",
    'public/app.css' => "body { color: teal; }\n",
    'README.md' => "new readme\n",
];

foreach ($files as $rel => $content) {
    $path = $src . '/' . $rel;
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, $content);
}

$zip = new ZipArchive();
$opened = $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($opened !== true) {
    fwrite(STDERR, "cannot create test zip\n");
    exit(1);
}
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $zip->addFile($file->getPathname(), 'pipnix-main/' . substr($file->getPathname(), strlen($src) + 1));
}
$zip->close();

// Existing installation: local state that must survive.
@mkdir($base . '/app', 0775, true);
@mkdir($base . '/public', 0775, true);
@mkdir($base . '/dags', 0775, true);
file_put_contents($base . '/app/bootstrap.php', "<?php // OLD\n");
file_put_contents($base . '/app/config.php', "<?php return ['db' => 'LOCAL-SECRET'];\n");
file_put_contents($base . '/public/index.php', "old front controller\n");
file_put_contents($base . '/dags/keep.py', "local pipeline\n");

$res = Updater::applyArchiveTo($archive, $base, false, [
    'version' => '9.9.9',
    'commit' => str_repeat('a', 40),
    'channel' => 'test',
], $work);

check('apply ok', !empty($res['ok']));
check('apply copied files', ($res['copied'] ?? 0) >= 3);
check('bootstrap replaced', str_contains((string) file_get_contents($base . '/app/bootstrap.php'), '9.9.9'));
check('css replaced', str_contains((string) file_get_contents($base . '/public/app.css'), 'teal'));
check('README replaced', is_file($base . '/README.md'));
check('config preserved', str_contains((string) file_get_contents($base . '/app/config.php'), 'LOCAL-SECRET'));
check('config not overwritten from archive', !str_contains((string) file_get_contents($base . '/app/config.php'), 'FROM-ARCHIVE'));
check('local dag preserved', is_file($base . '/dags/keep.py'));
check('archive dag not installed', !is_file($base . '/dags/user_dag.py'));
check('archive job not installed', !is_file($base . '/jobs/helper.py'));
check('archive log not installed', !is_file($base . '/logs/old.log'));
check('unchanged file kept', str_contains((string) file_get_contents($base . '/public/index.php'), 'old front controller'));

$backup = (string) ($res['backup'] ?? '');
check('backup dir created', $backup !== '' && is_dir($backup));
check('backup holds old bootstrap',
    $backup !== '' && str_contains((string) @file_get_contents($backup . '/app/bootstrap.php'), 'OLD'));
check('backup meta written', $backup !== '' && is_file($backup . '/meta.json'));
check('meta reports the new version', ($res['meta']['version'] ?? '') === '9.9.9');

// ------------------------------------------------------------ output & exit
rrmdir($tmp);

echo $failures === 0 ? "\nall checks passed\n" : "\n{$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
