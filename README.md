# Elance Provider for OAuth 2.0 Client

[![Latest Version](https://img.shields.io/github/release/stevenmaguire/oauth2-elance.svg?style=flat-square)](https://github.com/stevenmaguire/oauth2-elance/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/stevenmaguire/oauth2-elance/master.svg?style=flat-square)](https://travis-ci.org/stevenmaguire/oauth2-elance)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/stevenmaguire/oauth2-elance.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevenmaguire/oauth2-elance/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/stevenmaguire/oauth2-elance.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevenmaguire/oauth2-elance)
[![Total Downloads](https://img.shields.io/packagist/dt/stevenmaguire/oauth2-elance.svg?style=flat-square)](https://packagist.org/packages/stevenmaguire/oauth2-elance)

This package provides Elance OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

To install, use composer:

```
composer require stevenmaguire/oauth2-elance
```

## Usage

Usage is the same as The League's OAuth client, using `\Stevenmaguire\OAuth2\Client\Provider\Elance` as the provider.

### Authorization Code Flow

```php
$provider = new Stevenmaguire\OAuth2\Client\Provider\Elance([
    'clientId'          => '{elance-client-id}',
    'clientSecret'      => '{elance-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url'
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getId());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Oh dear...');
    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();
}
```

### Refreshing a Token

Once your application is authorized, you can refresh an expired token using a refresh token rather than going through the entire process of obtaining a brand new token. To do so, simply reuse this refresh token from your data store to request a refresh.

```php
$grant = new \League\OAuth2\Client\Grant\RefreshToken();
$token = $provider->getAccessToken($grant, ['refresh_token' => $refreshToken]);
```

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/stevenmaguire/oauth2-elance/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Steven Maguire](https://github.com/stevenmaguire)
- [All Contributors](https://github.com/stevenmaguire/oauth2-elance/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/stevenmaguire/oauth2-elance/blob/master/LICENSE) for more information.
