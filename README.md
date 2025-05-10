# ![](/icon.png) TinyEmotes

Free, easy-to-install custom emote provider for IRC (and Twitch) platforms!
The main goal of the project is to replicate full functionality of other emote providers *(7TV, BetterTTV and FrankerFaceZ)* and make it free for everyone. The design is inspired by booru imageboards.

## Features

+ Emote upload (GIF/WebP support, automatic resizing)
+ Moderation system (Reports, emote approval)

> The project is in public beta. See [TODO list](https://github.com/users/ilotterytea/projects/11) for upcoming features.

## Public TinyEmotes Instances

| URL | Description | SFW/NSFW | Category |
|-----|-------------|----------|----------|
| [alright.party](https://alright.party) | Official TinyEmotes instance | NSFW | Mixed

> PR if you know any instances running TinyEmotes.

## Software supporting TinyEmotes

+ [Tinyrino](https://github.com/ilotterytea/tinyrino) - a Chatterino fork made by the author of TinyEmotes.

> PR if you know any software supporting TinyEmotes.

## Installation guide

> It is not recommended to install right now before version 1.0 is released, as there may be breaking updates.

### Prerequisites

+ PHP >= 8.3
+ ImageMagick >= 7.0
+ cURL
+ MySQL/MariaDB

### Step-by-step

1. Clone the repository.
2. Import `database.sql` to your database.
3. Copy `src/config.sample.php` to `src/config.php` and set it up.
4. Use reverse proxy *(Nginx, Apache, etc.)* for the project. See [configuration examples](#reverse-proxy-configurations).
5. ???
6. Profit! It should work.

### Reverse proxy configurations

<details>
<summary>Basic Nginx configuration</summary>

```nginx
server {
    server_name tinyemotesinstance.com;

    root /www/tinyemotesinstance/public;
    index index.php;

    location ~ ^/static/?(.*)$ {
        root /www/tinyemotesinstance/public;
        try_files /custom_static/$1 /static/$1 =404;
    }

    location / {
	    try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
	    include fastcgi_params;
	    fastcgi_pass unix:/run/php/php-fpm.sock;
	    fastcgi_index index.php;
	    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|webp|ico)$ {
        expires 6M;
        access_log off;
        add_header Cache-Control "public";
    }

    location ~ /\. {
        deny all;
    }
}
```

</details>

## License

This project is under MPL-2.0 license. See [LICENSE](/LICENSE)