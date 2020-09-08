<?php


namespace App\Http\Services;

use App\Http\Repositories\AdWordsRepository;
use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201809\o\TargetingIdeaService;
use Google\AdsApi\Common\AdsLoggerFactory;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Illuminate\Support\Arr;

class AdWordsServiceFactory
{
    private static $DEFAULT_SOAP_LOGGER_CHANNEL = 'AW_SOAP';

    public static function createForConfig(array $adwordsConfig): AdWordsServices
    {
        $session = self::createAuthenticatedAdWordsSessionBuilder($adwordsConfig);

        $adWordsServices = new AdWordsServices();
        $targetingIdeaService = $adWordsServices->get($session, TargetingIdeaService::class);

        return self::createTargetingIdeaService($targetingIdeaService);
    }

    /**
     * @param array $config
     *
     * @return AdWordsSession
     *
     * Generate a refreshable OAuth2 credential for authentication.
     * Construct an API session
     */
    protected static function createAuthenticatedAdWordsSessionBuilder(array $config): AdWordsSession
    {
        $oAuth2Credential = (new OAuth2TokenBuilder())
            ->withClientId($config['client_id'])
            ->withClientSecret($config['client_secret'])
            ->withRefreshToken($config['client_refresh_token'])
            ->build();

        $soapLogger = (new AdsLoggerFactory())
            ->createLogger(
                self::$DEFAULT_SOAP_LOGGER_CHANNEL,
                Arr::get($config, 'soap_log_file_path', null),
                Arr::get($config, 'soap_log_level', 'ERROR')
            );

        $session = (new AdWordsSessionBuilder())
            ->withOAuth2Credential($oAuth2Credential)
            ->withDeveloperToken($config['developer_token'])
            ->withUserAgent($config['user_agent'])
            ->withClientCustomerId($config['client_customer_id'])
            ->withSoapLogger($soapLogger)
            ->build();

        return $session;
    }

    protected static function createTargetingIdeaService(TargetingIdeaService $targetingIdeaService): AdWordsServices
    {
        return new AdWordsServices($targetingIdeaService);
    }
}
