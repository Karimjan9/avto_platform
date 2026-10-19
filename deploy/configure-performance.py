#!/usr/bin/env python3
"""Configure only the Avto Platform services; do not change the shared Redis instance."""
import datetime
import os
from pathlib import Path
import pwd
import re
import secrets
import shutil
import subprocess
import time


def run(*args, **kwargs):
    return subprocess.run(args, check=True, text=True, **kwargs)


if os.geteuid() != 0:
    raise SystemExit('Run as root.')
base = Path('/var/www/avto-platform')
environment = base / 'shared/.env'
if not environment.is_file():
    raise SystemExit('Platform environment is missing.')
owner = pwd.getpwnam('avto_platform')
stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup = Path('/root/avto-deploy/backups') / ('performance-' + stamp)
backup.mkdir(mode=0o700)
shutil.copy2(environment, backup / 'platform.env')
os.chmod(backup / 'platform.env', 0o600)
config_dir = Path('/etc/avto-platform')
config_dir.mkdir(mode=0o750, exist_ok=True)
os.chown(config_dir, 0, owner.pw_gid)
data_dir = Path('/var/lib/avto-platform-redis')
data_dir.mkdir(mode=0o700, exist_ok=True)
os.chown(data_dir, owner.pw_uid, owner.pw_gid)
redis_config = config_dir / 'redis.conf'
if redis_config.exists():
    match = re.search(r'^requirepass (\S+)$', redis_config.read_text(), re.M)
    if not match:
        raise SystemExit('Existing dedicated Redis configuration needs inspection.')
    password = match.group(1)
else:
    password = secrets.token_urlsafe(48)
redis_config.write_text('\n'.join([
    'bind 127.0.0.1', 'port 6381', 'protected-mode yes', 'daemonize no',
    'dir /var/lib/avto-platform-redis', 'dbfilename dump.rdb', 'databases 4',
    'appendonly yes', 'appendfsync everysec', 'save 900 1',
    'maxmemory 256mb', 'maxmemory-policy noeviction', 'maxclients 2048',
    'timeout 300', 'tcp-keepalive 60', 'loglevel notice', 'logfile ""',
    'requirepass ' + password, '',
]))
os.chmod(redis_config, 0o640)
os.chown(redis_config, 0, owner.pw_gid)
service = Path('/etc/systemd/system/avto-platform-redis.service')
service.write_text('''[Unit]
Description=Avto Platform private Redis
After=network.target

[Service]
Type=simple
User=avto_platform
Group=avto_platform
ExecStart=/usr/bin/redis-server /etc/avto-platform/redis.conf
Restart=on-failure
RestartSec=2
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
PrivateDevices=true
ProtectHome=true
ProtectSystem=strict
ReadWritePaths=/var/lib/avto-platform-redis
RestrictAddressFamilies=AF_INET AF_INET6 AF_UNIX
MemoryMax=384M
LimitNOFILE=4096

[Install]
WantedBy=multi-user.target
''')
run('systemctl', 'daemon-reload')
run('systemctl', 'enable', '--now', 'avto-platform-redis.service')
redis_env = dict(os.environ, REDISCLI_AUTH=password)
for attempt in range(20):
    result = subprocess.run(['redis-cli', '-p', '6381', 'PING'], env=redis_env, text=True, capture_output=True)
    if result.returncode == 0 and result.stdout.strip() == 'PONG':
        break
    time.sleep(0.25)
else:
    raise SystemExit('Dedicated Redis health check failed; environment not changed.')
run('/usr/bin/php8.3', '-r', 'if (!extension_loaded("redis")) { exit(1); }')
values = {
    'CACHE_STORE': 'redis', 'SESSION_DRIVER': 'redis', 'SESSION_CONNECTION': 'default',
    'REDIS_CLIENT': 'phpredis', 'REDIS_HOST': '127.0.0.1', 'REDIS_PORT': '6381',
    'REDIS_PASSWORD': password, 'REDIS_DB': '0', 'REDIS_CACHE_DB': '1',
    'REDIS_PREFIX': 'avto_platform_', 'CACHE_PREFIX': 'avto_platform_cache_',
    'SANCTUM_STATEFUL_DOMAINS': 'platform.buxoroavto.uz',
}
content = environment.read_text()
for key, value in values.items():
    replacement = key + '=' + value
    content, count = re.subn(r'^' + re.escape(key) + r'=.*$', lambda _: replacement, content, flags=re.M)
    if not count:
        content += '\n' + replacement
temporary = environment.with_name('.env.performance-next')
temporary.write_text(content.rstrip() + '\n')
os.chmod(temporary, 0o600)
os.chown(temporary, owner.pw_uid, owner.pw_gid)
os.replace(temporary, environment)

pool = Path('/etc/php/8.3/fpm/pool.d/avto-platform.conf')
nginx = Path('/etc/nginx/sites-available/platform.buxoroavto.uz')
assets = base / 'shared/assets'
assets.mkdir(mode=0o755, exist_ok=True)
for source in (base / 'current/public/build/assets').glob('*'):
    if source.is_file():
        shutil.copy2(source, assets / source.name)
        os.chmod(assets / source.name, 0o644)
for path in [pool, nginx]:
    shutil.copy2(path, backup / path.name)
pool.write_text((Path(__file__).parent / 'php-fpm.conf').read_text())
current_nginx = nginx.read_text()
template = (Path(__file__).parent / 'nginx.conf').read_text()
if 'ssl_certificate' not in current_nginx:
    nginx.write_text(template)
else:
    # Preserve certificates and redirects when TLS has already been activated.
    if 'gzip_types' not in current_nginx:
        current_nginx = current_nginx.replace('client_max_body_size 64m;', 'client_max_body_size 64m;\n    gzip on;\n    gzip_vary on;\n    gzip_min_length 1024;\n    gzip_comp_level 5;\n    gzip_types text/css application/javascript application/json image/svg+xml;')
    if 'location ^~ /build/' not in current_nginx:
        current_nginx = current_nginx.replace('location = /favicon.ico', 'location ^~ /build/ { try_files $uri =404; expires 1y; add_header Cache-Control "public, immutable"; add_header X-Content-Type-Options "nosniff" always; access_log off; }\n    location = /favicon.ico')
    if 'location ^~ /build/assets/' not in current_nginx:
        current_nginx = current_nginx.replace('location ^~ /build/', 'location ^~ /build/assets/ { alias /var/www/avto-platform/shared/assets/; gzip_static on; expires 1y; add_header Cache-Control "public, immutable"; add_header X-Content-Type-Options "nosniff" always; access_log off; }\n    location ^~ /build/')
    nginx.write_text(current_nginx)
try:
    run('/usr/sbin/php-fpm8.3', '-t')
    run('/usr/sbin/nginx', '-t')
except subprocess.CalledProcessError:
    shutil.copy2(backup / pool.name, pool)
    shutil.copy2(backup / nginx.name, nginx)
    shutil.copy2(backup / 'platform.env', environment)
    os.chown(environment, owner.pw_uid, owner.pw_gid)
    raise
run('systemctl', 'reload', 'php8.3-fpm')
run('systemctl', 'reload', 'nginx')
print('Platform-only performance services configured; secrets were not printed.')
print('Backup:', backup)
