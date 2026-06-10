<?php

namespace Blalmal10a\DbZip;

use Composer\Script\Event;

class Installer
{
    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();

        $base = self::guessAppUrl();

        $io->write('<info>====================================================</info>');
        $io->write('<info>  db-zip installed successfully!                   </info>');
        $io->write('<info>====================================================</info>');
        $io->write("  <comment>1.</comment> Visit {$base}/backup to backup and download .zip files");
        $io->write("  <comment>2.</comment> Visit {$base}/restore to restore the downloaded zip file");
    }

    private static function guessAppUrl(): string
    {
        $envPath = getcwd() . '/.env';

        if (file_exists($envPath)) {
            $env = file_get_contents($envPath);
            if (preg_match('/^APP_URL=(.+)$/m', $env, $matches)) {
                return rtrim(trim($matches[1]), '/');
            }
        }

        return getenv('APP_URL') ? rtrim(getenv('APP_URL'), '/') : 'http://localhost';
    }
}
