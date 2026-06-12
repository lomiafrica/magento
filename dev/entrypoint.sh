#!/bin/bash
set -e

if [ ! -f /var/www/html/app/etc/env.php ]; then
    echo "Magento not installed yet. Waiting for database..."
    until php -r "new PDO('mysql:host=db;dbname=magento', 'magento', 'magento');" 2>/dev/null; do
        echo "Waiting for MariaDB..."
        sleep 3
    done

    su -s /bin/bash www-data -c "/var/www/html/bin/magento setup:install \
        --base-url=http://localhost:8080/ \
        --backend-frontname=admin \
        --db-host=db \
        --db-name=magento \
        --db-user=magento \
        --db-password=magento \
        --admin-firstname=Admin \
        --admin-lastname=User \
        --admin-email=admin@example.com \
        --admin-user=admin \
        --admin-password=Admin12345! \
        --language=en_US \
        --currency=USD \
        --timezone=UTC \
        --use-rewrites=1 \
        --search-engine=opensearch \
        --opensearch-host=search \
        --opensearch-port=9200"
fi

exec apache2-foreground
