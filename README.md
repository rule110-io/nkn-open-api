<p align="center"><img src="https://avatars0.githubusercontent.com/u/64492989?s=200&v=4" width="150"></p>

<p align="center">
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License"></a>
</p>

## About NKN open API

The NKN open API is a blockchain-to-database parser with an easy to use interface written in PHP. We're using [Laravel](https://laravel.com/) as our framework to provide a clean and maintainable code.

This API gives you all the handy features you need to code your app such as:

- History queries of all ever created blocks
- Already parsed and human readable payloads
- A websocket interface to pub/sub to latest block updates
- and many more

You can also host NKN open API by yourself!

## I don't want to host it by myself - how can I get started fast?

If you want to jump right into app developing it's a good idea to check out our documentation. All api endpoints are already documented here.

Missing a feature? Just create an Issue for that and we'll implement it!

## Prerequisities

If you want to host your own NKN API by yourself you at least need

- [Composer](https://getcomposer.org/) installed
- A configured web server ([Nginx](https://www.howtoforge.com/tutorial/ubuntu-laravel-php-nginx/) or [Apache](https://www.howtoforge.com/tutorial/install-laravel-on-ubuntu-for-apache/))
- At least PHP 7.2.5
-- redis extension
- [PostgreSQL](https://www.postgresql.org/) database server
- A [Redis](https://redis.io/) database for queues

If you're not that much into setting your system up you can always use a virtual machine with [Laravel Homestead](https://laravel.com/docs/master/homestead) if you're on Windows/Linux or [Laravel Valet](https://laravel.com/docs/master/valet) when you're on a Mac.

## Getting started


#### 1. Install dependencies
Run ``composer install``to install all dependencies

#### 2. Setup .env file
Create a file called ``.env`` with the basic variables (Change them to your needs):

```
APP_NAME="NKN Open API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=YOUR_URL_HERE

LOG_CHANNEL=stack

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=PGSQL_DB_NAME
DB_USERNAME=PGSQL_USER_NAME
DB_PASSWORD=PGSQL_USER_PASSWORD

BROADCAST_DRIVER=pusher
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=REDIS_PASSWORD
REDIS_PORT=6379

PUSHER_APP_ID=RANDOM_APP_ID 
PUSHER_APP_KEY=RANDOM_APP_KEY
PUSHER_APP_SECRET=RANDOM_APP_SECRET
PUSHER_APP_CLUSTER="mt1"


UPDATEINTERVAL = 20
REMOTENODE_ADDR = PUBLIC_NODE_ADDR (f.e. 'https://mainnet-rpc-node-0001.nkn.org/mainnet/api/wallet', better results with a local node)
REMOTENODE_PORT = 30003


```

You can also just rename ``.env.example`` to get the configurations of the public available API.

#### 3. Create your App-Key 
```php artisan key:generate```

#### 4. Setup the database structure
 ```php artisan migrate```

#### 5. Start background services 
##### 5.1 Start the scheduler
 ```php artisan horizon```

##### 5.2 Start the websocket-server
 ```php artisan websockets:serve```

#### 6. Run the first blockchain sync (takes a looooong time)
```php artisan blockchain:init```

#### 7. Setup a regular cronjob for job parsing
Setup an "every minute" job that runs ```php artisan schedule:run``` to get regular updates in time.



## Contributing

Wow! That is very nice of you! The contribution guide will come shortly. Meanwhile join us on the [official NKN Discord channel](https://discord.gg/skHx9S) and let us know your thoughts.

## License

The NKN open API is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
