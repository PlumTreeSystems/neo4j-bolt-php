### Setting up a driver and creating a session

```php

use PTS\Bolt\Driver;
use PTS\Bolt\Configuration;

$driver = new Driver(Configuration::create()->withUri("bolt://localhost"));
$session = $driver->session();
```

### Sending a Cypher statement

```php
$session = $driver->session();
$session->run("CREATE (n)");
$session->close();

// with parameters :

$session->run("CREATE (n) SET n += {props}", ['name' => 'Mike', 'age' => 27]);
```



### TLS Encryption

In order to enable TLS support, you need to set the configuration option to `REQUIRED` or pass `tls=true` option to URI, here an example :

```php
$config = \PTS\Bolt\Configuration::create()
    ->withUri('bolt://localhost')
    ->withTLSMode(\PTS\Bolt\Configuration::TLSMODE_REQUIRED);

// or

$config = \PTS\Bolt\Configuration::create()
    ->withUri('bolt://localhost?tls=true');

$driver = new \PTS\Bolt\Driver($config);
$session = $driver->session();
```

For self signed certificates use `TLSMODE_REQUIRED_NO_VALIDATION` or `validate_tls=false`.

### Database selection

Database can be selected via URI or `withDatabase` method :
```php
$config = \PTS\Bolt\Configuration::create()
    ->withUri('bolt://localhost')
    ->withDatabase('test');

// or

$config = \PTS\Bolt\Configuration::create()
    ->withUri('bolt://localhost/test');

$driver = new \PTS\Bolt\Driver($config);
$session = $driver->session();
```

### Bookmarks

You can fetch bookmarks from last result from summary or from session using `lastBookmark` method. To pass bookmarks for new session use `withBookmarks` method :
```php
$result = $session1->run('...');

$bookmark = $result->getSummary()->getBookmark()
// or
$bookmark = $session1->getLastBookmark();

$config = \PTS\Bolt\Configuration::create()
    ->withUri('bolt://localhost')
    ->withBookmarks([$bookmark]);

$driver = new \PTS\Bolt\Driver($config);
$session2 = $driver->session();
```