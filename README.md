# RemoteAuth PHP SDK

This library is a wrapper for calling the RemoteAuth API.

## How it works

* Initialize a new `Client` instance.
* When calling API methods, you must pass the `RemoteAuthUser` instance of the user you are authenticating as.
* The Client will automatically attempt to refresh access tokens if they expire.

### Client initialization

You can create a new instance of a Client by passing the following options:

```php
$client = new Client([
    'baseUrl' => 'https://app.remoteauth.com',
    'clientId' => 'XXX',
    'clientSecret' => 'XXX',
    'scope' => ''
], $cache);
```

#### Options

* **baseUrl** - The URL of the RemoteAuth server you are using.
* **clientId** - The Client ID of your OAuth Client. This can be obtained from within the RemoteAuth UI.
* **clientSecret** - The Client Secret of your OAuth Client. This can be obtained from within the RemoteAuth UI.
* **scope** - The scopes you'd like to request when an access token is refreshed.

`$cache` parameter:

The second argument to the `Client` constructor is an optional argument that must be an instance of `Psr\SimpleCache\CacheInterface`.

## Methods

The Client exposes the following methods. All methods must be passed the `RemoteAuthUser` you are authenticating as. Optional arguments for payload and ignoring cached request results.

* `applicationMembersByToken($user, $ignoreCache)` - Returns the ApplicationMember records that exist between the User and the Application attached to the given token.

