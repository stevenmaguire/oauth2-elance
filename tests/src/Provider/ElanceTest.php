<?php namespace Stevenmaguire\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery as m;

class ElanceTest extends \PHPUnit_Framework_TestCase
{
    use QueryBuilderTrait;

    protected $provider;

    protected function setUp()
    {
        $this->provider = new \Stevenmaguire\OAuth2\Client\Provider\Elance([
            'clientId' => 'mock_client',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testNotProvidingScopesIncludesDefault()
    {
        $url = $this->provider->getAuthorizationUrl();

        $this->assertContains('basicInfo', $url);
    }

    public function testProvidingScopesOverridesDefault()
    {
        $scopeSeparator = ',';
        $options = ['scope' => [uniqid(), uniqid()]];
        $query = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url = $this->provider->getAuthorizationUrl($options);
        $encodedScope = $this->buildQueryString($query);
        $this->assertContains($encodedScope, $url);
    }

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertEquals('/api2/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('/api2/oauth/token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn('{"data":{"access_token":"mock_access_token","expires_in":3600,"token_type":"bearer","refresh_token":"mock_refresh_token","scope":"basicInfo"}}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertLessThanOrEqual(time() + 3600, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
        $this->assertEquals('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testUserData()
    {
        $username = uniqid();
        $picture = uniqid();
        $userId = rand(1000,9999);

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"data":{"access_token":"mock_access_token","expires_in":3600,"token_type":"bearer","refresh_token":"mock_refresh_token","scope":"basicInfo"}}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($this->getUserResponse($userId, $username, $picture));
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userId, $user->getId());
        $this->assertEquals($userId, $user->toArray()['data']['providerProfile']['userId']);
        $this->assertEquals($username, $user->getUsername());
        $this->assertEquals($username, $user->toArray()['data']['providerProfile']['userName']);
        $this->assertEquals($picture, $user->getAvatarUrl());
        $this->assertEquals($picture, $user->toArray()['data']['providerProfile']['logo']);
    }

    /**
     *
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function testUserDataFails()
    {
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"data":{"access_token":"mock_access_token","expires_in":3600,"token_type":"bearer","refresh_token":"mock_refresh_token","scope":"basicInfo"}}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn('{"errors": [{"type": "validation","code": "E_VALIDATION_INVALID_ID","description": "Input not a valid ID."}, {"type": "validation","code": "E_API_NOT_AUTHORIZED","description": "You are not authorized to access this data."}]}');
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getReasonPhrase')->andReturn('It broken');
        $userResponse->shouldReceive('getStatusCode')->andReturn(500);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);
    }

    protected function getUserResponse($userId, $userName, $logo)
    {
        return '
        {
            "data": {
                "providerProfile": {
                    "userId": "'.$userId.'",
                    "userName": "'.$userName.'",
                    "businessName": "Ted Mosby",
                    "companyUserId": null,
                    "companyLoginName": null,
                    "companyBusinessName": null,
                    "tagLine": "Professional PHP Development",
                    "overview": "Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                    "hourlyRate": "33",
                    "isIndividual": true,
                    "isWatched": false,
                    "isStaff": false,
                    "city": "Seattle",
                    "state": "WA",
                    "country": "United States",
                    "countryCode": "US",
                    "profileType": {
                        "name": "Individual",
                        "icon": "&lt;div class=&quot;sprProfImg sprProf-individual-icon-xxxsmall-free&quot;&gt;&lt;!-- --&gt;&lt;\/div&gt;",
                        "fullName": "Basic Individual",
                        "code": "indiv"
                    },
                    "skills": {},
                    "skillsCount": 5,
                    "portfolioCount": 0,
                    "privateEarnings": false,
                    "earnings6Months": "0",
                    "feedback6Months": "0",
                    "posFeedback6Months": "0",
                    "avgFeedbackScore6Months": "0.0",
                    "earnings12Months": "0",
                    "feedback12Months": "0",
                    "jobs12Months": "2",
                    "posFeedback12Months": null,
                    "avgFeedbackScore12Months": "0.0",
                    "elanceLevel": null,
                    "category": "All Categories",
                    "userCategories": [{
                        "id": "10183",
                        "name": "IT & Programming"
                    }],
                    "logo": "'.$logo.'",
                    "providerProfileURL": "https:\/\/elance.com\/s\/t_mosby\/",
                    "providerJobHistoryURL": "https:\/\/elance.com\/s\/t_mosby\/job-history\/",
                    "providerPortfolioURL": "https:\/\/elance.com\/s\/t_mosby\/portfolio",
                    "earnings": "0",
                    "feedback": "0",
                    "posFeedback": "0",
                    "avgFeedbackScore": "0.0",
                    "clients6Months": "0",
                    "clients": "0",
                    "repeatClients": "0",
                    "RepeatClients": "0",
                    "repeatClients6Months": "0",
                    "repeatClients12Months": "0",
                    "repeatClientsPct": 0,
                    "repeatClientsPct6Months": 0,
                    "repeatClientsPct12Months": 0,
                    "jobs6Months": "2",
                    "jobs": "2",
                    "milestones6Months": "0",
                    "milestones": "0",
                    "hoursWorked6Months": "0",
                    "hoursWorked": "0",
                    "earningsPerClient": 0,
                    "earningsPerClient6Months": 0,
                    "earningsPerClient12Months": 0,
                    "clients12Months": "0",
                    "milestones12Months": "0",
                    "hoursWorked12Months": "0",
                    "endorsement": "N\/A",
                    "endorsement6Months": "N\/A",
                    "endorsement12Months": "N\/A",
                    "latestJobs": {
                        "0": {
                            "jobId": 30561768,
                            "name": "PHP Development Required ASAP",
                            "budget": " $1,000 - $5,000",
                            "budgetMin": "1000",
                            "budgetMax": "5000",
                            "startDate": "05\/22\/2012",
                            "feedbackDate": 1337659200,
                            "bidAmount": "0",
                            "isHourly": 0,
                            "subcategory": "Web Programming",
                            "status": "Working",
                            "timeLeft": "Closed",
                            "jobURL": "https:\/\/elance.com\/j\/php-development-required-asap\/30561768\/"
                        },
                        "1": {
                            "jobId": 30561771,
                            "name": "Hourly PHP Project",
                            "budget": "$40 - $50 \/ hr",
                            "budgetMin": "1200",
                            "budgetMax": "2000",
                            "startDate": "05\/22\/2012",
                            "feedbackDate": 1337659200,
                            "bidAmount": "0",
                            "isHourly": 1,
                            "hourlyRateCode": "14224",
                            "hourlyRateMin": "40",
                            "hourlyRateMax": "50",
                            "subcategory": "Web Programming",
                            "status": "Working",
                            "timeLeft": "Closed",
                            "jobURL": "https:\/\/elance.com\/j\/hourly-php-project\/30561771\/"
                        }
                    },
                    "portfolio": {
                        "0": {
                            "url": "/media/images/spacer.gif",
                            "title": "[MS-ASAIRS]",
                            "description": "ActiveSync AirSyncBase Namespace Protocol Specification",
                            "tags": "activesync xml devices iphone android",
                            "websiteurl": "http://msdn.microsoft.com/en-us/library/dd299454(v=exchg.80).aspx",
                            "fullurl": "https://elance.com/samples/ms-asairs-activesync-xml-devices-iphone-android/40202562/"
                        }
                    },
                    "testimonials": {
                        "0": {
                            "buyerName": "employer01",
                            "buyerProfileURL": "https://elance.com/e/employer01/",
                            "comments": "Excellent job!!"
                        }
                    },
                    "timezoneData": {
                        "name": "America\/New_York",
                        "gmt_offset": "-05:00",
                        "city": "Elance (ET)"
                    }
                }
            }
        }';
    }
}
