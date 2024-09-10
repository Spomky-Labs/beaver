<?php

declare(strict_types=1);

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use function Castor\context;
use function Castor\io;
use function Castor\notify;
use function Castor\run;

#[AsTask(description: 'Run mutation testing.')]
function infect(int $minMsi = 0, int $minCoveredMsi = 0, bool $ci = false): void
{
    io()->title('Running infection');
    $nproc = run('nproc', context: context()->withQuiet());
    if (! $nproc->isSuccessful()) {
        io()->warning('Cannot determine the number of processors. Setting 1 thread.');
        $threads = '1';
    } else {
        $threads = (int) $nproc->getOutput();
    }
    $command = [
        'php',
        'vendor/bin/infection',
        sprintf('--min-msi=%s', $minMsi),
        sprintf('--min-covered-msi=%s', $minCoveredMsi),
        sprintf('--threads=%s', $threads),
    ];
    if ($ci) {
        $command[] = '--logger-github';
        $command[] = '-s';
    }
    $context = context();
    $context->withEnvironment([
        'XDEBUG_MODE' => 'coverage',
    ]);
    run($command, context: $context);
}

#[AsTask(description: 'Run PHPUnit tests.')]
function test(bool $coverageHtml = false, bool $coverageText = false, null|string $group = null): void
{
    io()->title('Running tests');
    $command = ['php', 'vendor/bin/phpunit', '--color'];
    $context = context();
    $context->withEnvironment([
        'XDEBUG_MODE' => 'off',
    ]);
    if ($coverageHtml) {
        $command[] = '--coverage-html=build/coverage';
        $context->withEnvironment([
            'XDEBUG_MODE' => 'coverage',
        ]);
    }
    if ($coverageText) {
        $command[] = '--coverage-text';
        $context->withEnvironment([
            'XDEBUG_MODE' => 'coverage',
        ]);
    }
    if ($group !== null) {
        $command[] = sprintf('--group=%s', $group);
    }
    run($command, context: $context);
}

#[AsTask(description: 'Coding standards check.')]
function cs(bool $fix = false): void
{
    io()->title('Running coding standards check');
    $command = ['php', 'vendor/bin/ecs', 'check'];
    $context = context();
    $context->withEnvironment([
        'XDEBUG_MODE' => 'off',
    ]);
    if ($fix) {
        $command[] = '--fix';
    }
    run($command, context: $context);
}

#[AsTask(description: 'Running PHPStan.')]
function stan(bool $baseline = false): void
{
    io()->title('Running PHPStan');
    $options = ['analyse'];
    if ($baseline) {
        $options[] = '--generate-baseline';
    }
    $command = ['php', 'vendor/bin/phpstan', ...$options];
    $context = context();
    $context->withEnvironment([
        'XDEBUG_MODE' => 'off',
    ]);
    run($command, context: $context);
}

#[AsTask(description: 'Validate Composer configuration.')]
function validate(): void
{
    io()->title('Validating Composer configuration');
    $command = ['composer', 'validate', '--strict'];
    $context = context();
    $context->withEnvironment([
        'XDEBUG_MODE' => 'off',
    ]);
    run($command, context: $context);

    $command = ['composer', 'dump-autoload', '--optimize', '--strict-psr'];
    run($command, context: $context);
}

/**
 * @param array<string> $allowedLicenses
 */
#[AsTask(description: 'Check licenses.')]
function checkLicenses(
    array $allowedLicenses = ['Apache-2.0', 'BSD-2-Clause', 'BSD-3-Clause', 'ISC', 'MIT', 'MPL-2.0', 'OSL-3.0']
): void {
    io()->title('Checking licenses');
    $allowedExceptions = [];
    $command = ['composer', 'licenses', '-f', 'json'];
    $context = context();
    $context->withEnvironment([
        'XDEBUG_MODE' => 'off',
    ]);
    $context->withQuiet();
    $result = run($command, context: $context);
    if (! $result->isSuccessful()) {
        io()->error('Cannot determine licenses');
        exit(1);
    }
    $licenses = json_decode($result->getOutput(), true);
    $disallowed = array_filter(
        $licenses['dependencies'],
        static fn (array $info, $name) => ! in_array($name, $allowedExceptions, true)
            && count(array_diff($info['license'], $allowedLicenses)) === 1,
        \ARRAY_FILTER_USE_BOTH
    );
    $allowed = array_filter(
        $licenses['dependencies'],
        static fn (array $info, $name) => in_array($name, $allowedExceptions, true)
            || count(array_diff($info['license'], $allowedLicenses)) === 0,
        \ARRAY_FILTER_USE_BOTH
    );
    if (count($disallowed) > 0) {
        io()
            ->table(
                ['Package', 'License'],
                array_map(
                    static fn ($name, $info) => [$name, implode(', ', $info['license'])],
                    array_keys($disallowed),
                    $disallowed
                )
            );
        io()
            ->error('Disallowed licenses found');
        exit(1);
    }
    io()
        ->table(
            ['Package', 'License'],
            array_map(
                static fn ($name, $info) => [$name, implode(', ', $info['license'])],
                array_keys($allowed),
                $allowed
            )
        );
    io()
        ->success('All licenses are allowed');
}

#[AsTask(description: 'Run Rector to upgrade code.')]
function rector(bool $fix = false): void
{
    io()->title('Running Rector');
    $command = ['php', 'vendor/bin/rector', 'process', '--ansi'];
    if (! $fix) {
        $command[] = '--dry-run';
    }
    $context = context();
    $context->withEnvironment([
        'XDEBUG_MODE' => 'off',
    ]);
    run($command, context: $context);
}

#[AsTask(description: 'Run Deptrac to analyze dependencies.')]
function deptrac(): void
{
    io()->title('Running Rector');
    $command = ['php', 'vendor/bin/deptrac', 'analyse', '--fail-on-uncovered', '--no-cache'];
    $context = context();
    $context->withEnvironment([
        'XDEBUG_MODE' => 'off',
    ]);
    run($command, context: $context);
}

#[AsTask(description: 'Restart the containers.')]
function restart(): void
{
    stop();
    start();
}

#[AsTask(description: 'Clean the infrastructure (remove container, volume, networks).')]
function destroy(bool $force = false): void
{
    if (! $force) {
        io()->warning('This will permanently remove all containers, volumes, networks... created for this project.');
        io()
            ->comment('You can use the --force option to avoid this confirmation.');

        if (! io()->confirm('Are you sure?', false)) {
            io()->comment('Aborted.');

            return;
        }
    }

    run('docker-compose down -v --remove-orphans --volumes --rmi=local');
    notify('The infrastructure has been destroyed.');
}

#[AsTask(description: 'Stops and removes the containers.')]
function stop(): void
{
    run(['docker', 'compose', 'down']);
}

#[AsTask(description: 'Starts the containers.')]
function start(): void
{
    run(['docker', 'compose', 'up', '-d']);
    frontend(true);
}

#[AsTask(description: 'Build the images.')]
function build(): void
{
    run(['docker', 'compose', 'build', '--no-cache', '--pull']);
}

#[AsTask(description: 'Compile the frontend.')]
function frontend(bool $watch = false): void
{
    $consoleOutput = run(['bin/console'], context: context()->withQuiet());
    $commandsToRun = [
        'assets:install' => [],
        'importmap:install' => [],
        'tailwind:build' => $watch ?['--watch'] : [],
        'asset-map:compile' => [],
    ];

    foreach ($commandsToRun as $command => $arguments) {
        if (str_contains($consoleOutput->getOutput(), $command)) {
            php(['bin/console', $command, ...$arguments]);
        }
    }
    if (file_exists('yarn.lock')) {
        run(['yarn', 'install']);
        run(['yarn', $watch ? 'watch' : 'build']);
    }
}

#[AsTask(description: 'Update the dependencies and other features.')]
function update(): void
{
    run(['composer', 'update']);
    $consoleOutput = run(['bin/console'], context: context()->withQuiet());
    $commandsToRun = [
        'doctrine:migrations:migrate' => [],
        'doctrine:schema:validate' => [],
        'doctrine:fixtures:load' => [],
        'geoip2:update' => [],
        'app:browscap:update' => [],
        'importmap:update' => [],
    ];

    foreach ($commandsToRun as $command => $arguments) {
        if (str_contains($consoleOutput->getOutput(), $command)) {
            php(['bin/console', $command, ...$arguments]);
        }
    }
}

#[AsTask(description: 'Runs a Consumer from the Docket Container.')]
function consume(): void
{
    php(['bin/console', 'messenger:consume', '--all']);
}

#[AsTask(description: 'Runs a Symfony Console command from the Docket Container.', ignoreValidationErrors: true)]
function console(#[AsRawTokens] array $args = []): void
{
    php(['bin/console', ...$args]);
}

#[AsTask(description: 'Runs a PHP command from the Docket Container.', ignoreValidationErrors: true)]
function php(#[AsRawTokens] array $args = []): void
{
    run(['docker', 'compose', 'exec', '-T', 'php', ...$args]);
}
