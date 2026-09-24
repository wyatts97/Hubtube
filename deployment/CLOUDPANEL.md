# HubTube on CloudPanel

CloudPanel runs each site as its own unprivileged **site user**, who owns the
site's files. Everything below runs as that user over SSH, from the site's
folder (`/home/<site-user>/htdocs/<domain>`). Nothing here needs `sudo`, and
nothing uses `www-data`.

The general guide is [DEPLOY.md](./DEPLOY.md). This page covers only what is
different on CloudPanel.

## One-time setup

**Vhost.** In CloudPanel → Sites → your site → Vhost, keep CloudPanel's
template, and add from [nginx/hubtube.conf](./nginx/hubtube.conf):

- the block that refuses `.php` under `/storage` and `/uploads` (above the
  `\.php$` location)
- the "Private videos" block, if you use private videos with nginx protection

**Scheduler.** CloudPanel → Sites → your site → Cron Jobs, every minute:

```bash
php /home/<site-user>/htdocs/<domain>/artisan schedule:run >> /dev/null 2>&1
```

**Horizon and Reverb** must always be running. Pick one:

- *Supervisor (best).* Someone with root adds this once to
  `/etc/supervisor/conf.d/hubtube.conf`. After that, the site user never needs
  root, because restarts go through artisan (below).

  ```ini
  [program:hubtube-horizon]
  command=php /home/<site-user>/htdocs/<domain>/artisan horizon
  user=<site-user>
  autostart=true
  autorestart=true
  stopwaitsecs=3600
  stopsignal=QUIT
  stdout_logfile=/home/<site-user>/htdocs/<domain>/storage/logs/horizon-supervisor.log

  [program:hubtube-reverb]
  command=php /home/<site-user>/htdocs/<domain>/artisan reverb:start --host=127.0.0.1 --port=8080
  user=<site-user>
  autostart=true
  autorestart=true
  stdout_logfile=/home/<site-user>/htdocs/<domain>/storage/logs/reverb-supervisor.log
  ```

- *No root at all.* Add Cron Jobs that start each one if it is not running:

  ```bash
  pgrep -u <site-user> -f "artisan horizon" >/dev/null || (cd /home/<site-user>/htdocs/<domain> && nohup php artisan horizon >> storage/logs/horizon.log 2>&1 &)
  pgrep -u <site-user> -f "artisan reverb:start" >/dev/null || (cd /home/<site-user>/htdocs/<domain> && nohup php artisan reverb:start --host=127.0.0.1 --port=8080 >> storage/logs/reverb.log 2>&1 &)
  ```

## Updating

```bash
git pull origin master
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan down --retry=15
php artisan migrate --force
php artisan up
php artisan translations:clear-cache
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan horizon:terminate
php artisan reverb:restart
find public/build/assets -type f -mtime +7 -delete
```

`horizon:terminate` and `reverb:restart` stop the running processes
gracefully, and Supervisor (or the cron check) starts them again on the new
code. Then reload PHP-FPM from CloudPanel (or ask whoever has root), so PHP
stops serving cached copies of the old code.

Check the release notes for anything a release needs beyond this:
[RELEASE-NOTES.md](./RELEASE-NOTES.md).

## Backups

The nightly backup covers the database and `.env`, not media. See
[DEPLOY.md → Backups](./DEPLOY.md#backups) for an off-site copy and for
backing up media with rclone; both work from the site user's crontab.
