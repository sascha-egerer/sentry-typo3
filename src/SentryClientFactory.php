<?php

declare(strict_types=1);

namespace Helhum\SentryTypo3;

use Composer\InstalledVersions;
use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Log\LoggerInterface;
use Sentry\Client as SentryClient;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\Integration\IntegrationInterface;
use Sentry\Transport\DefaultTransportFactory;
use TYPO3\CMS\Core\Core\Environment;

final class SentryClientFactory
{
    private const SDK_IDENTIFIER =  SentryClient::SDK_IDENTIFIER . ' - typo3';
    private const SDK_VERSION = SentryClient::SDK_VERSION;

    /**
     * @param IntegrationInterface[] $integrations
     */
    public function __construct(
        private readonly array $integrations,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createClient(): ClientInterface
    {
        $projectPath = Environment::getProjectPath();
        $defaultOptions = [
            'dsn' => $_SERVER['SENTRY_DSN'] ?? null,
            'in_app_exclude' => [
                $projectPath . '/private',
                $projectPath . '/public',
                $projectPath . '/var',
                $projectPath . '/vendor',
            ],
            'prefixes' => [
                $projectPath . '/public',
                $projectPath . '/private',
                $projectPath,
            ],
            'environment' => $_SERVER['SENTRY_ENVIRONMENT'] ?? $GLOBALS['TYPO3_CONF_VARS']['SYS']['environment'] ?? str_replace('/', '-', (string)(Environment::getContext())),
            'release' => $_SERVER['SENTRY_RELEASE'] ?? InstalledVersions::getPrettyVersion(InstalledVersions::getRootPackage()['name']),
            'send_default_pii' => false,
            'attach_stacktrace' => true,
            'error_types' => E_ALL & ~(E_STRICT | E_NOTICE | E_DEPRECATED | E_USER_DEPRECATED),
        ];
        $options = array_replace($defaultOptions, $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sentry'] ?? []);
        $options['integrations'] = $this->integrations;
        $options['default_integrations'] = false;

        $typo3SentryVersion = InstalledVersions::getPrettyVersion('helhum/sentry-typo3');
        $sdkVersion = self::SDK_VERSION . ' - ' . $typo3SentryVersion;
        $clientBuilder = ClientBuilder::create($options);
        $clientBuilder->setSdkIdentifier(self::SDK_IDENTIFIER);
        $clientBuilder->setSdkVersion($sdkVersion);
        $clientBuilder->setLogger($this->logger);

        return $clientBuilder->getClient();
    }
}
