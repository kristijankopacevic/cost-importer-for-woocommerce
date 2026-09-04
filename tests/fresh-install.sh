#!/usr/bin/env bash
set -euo pipefail

cleanup() {
  if [[ "${CIWC_KEEP_CONTAINERS:-0}" == "1" ]]; then
    return
  fi
  docker rm -f ciwc-wp ciwc-db >/dev/null 2>&1 || true
  docker network rm ciwc-test >/dev/null 2>&1 || true
}
trap cleanup EXIT

pull_image() {
  local image="$1"
  local attempt

  # Docker Hub occasionally returns a transient 5xx response to a manifest
  # request on hosted runners. Retry the pull before classifying the test as a
  # product failure.
  for attempt in {1..4}; do
    if docker pull "$image"; then
      return 0
    fi
    sleep "$((attempt * 3))"
  done

  return 1
}

docker network create ciwc-test >/dev/null
pull_image mariadb:11
pull_image wordpress:php8.2-apache
pull_image wordpress:cli-php8.2
docker run -d --name ciwc-db --network ciwc-test \
  -e MARIADB_DATABASE=wordpress -e MARIADB_USER=wordpress -e MARIADB_PASSWORD=wordpress -e MARIADB_ROOT_PASSWORD=root \
  mariadb:11 >/dev/null
docker run -d --name ciwc-wp --network ciwc-test -p 8080:80 \
  -e WORDPRESS_DB_HOST=ciwc-db:3306 -e WORDPRESS_DB_USER=wordpress -e WORDPRESS_DB_PASSWORD=wordpress -e WORDPRESS_DB_NAME=wordpress \
  -v "$GITHUB_WORKSPACE/dist/cost-importer-for-woocommerce.zip:/tmp/cost-importer-for-woocommerce.zip:ro" \
  wordpress:php8.2-apache >/dev/null

for attempt in {1..30}; do
  if curl --fail --silent http://127.0.0.1:8080/wp-admin/install.php >/dev/null; then break; fi
  sleep 2
done
curl --fail --silent http://127.0.0.1:8080/wp-admin/install.php >/dev/null

# The entrypoint sets its own permissions while it starts. Once it is ready,
# make only its transient directories writable for plugin installation.
docker exec ciwc-wp sh -c 'mkdir -p /var/www/html/wp-content/upgrade /var/www/html/wp-content/uploads; chmod 777 /var/www/html/wp-content /var/www/html/wp-content/plugins /var/www/html/wp-content/upgrade /var/www/html/wp-content/uploads'

wp() {
  docker run --rm --network ciwc-test --volumes-from ciwc-wp \
    -e WORDPRESS_DB_HOST=ciwc-db:3306 -e WORDPRESS_DB_USER=wordpress -e WORDPRESS_DB_PASSWORD=wordpress -e WORDPRESS_DB_NAME=wordpress \
    wordpress:cli-php8.2 wp --path=/var/www/html --allow-root "$@"
}

wp core install --url=http://127.0.0.1:8080 --title='CIWC test' --admin_user=admin --admin_password=password --admin_email=admin@example.test --skip-email
wp plugin install woocommerce --activate
wp plugin install /tmp/cost-importer-for-woocommerce.zip --activate
wp plugin is-active cost-importer-for-woocommerce
wp plugin deactivate cost-importer-for-woocommerce
wp plugin is-active cost-importer-for-woocommerce && exit 1
wp plugin activate cost-importer-for-woocommerce
wp plugin is-active cost-importer-for-woocommerce
wp plugin install plugin-check --activate
plugin_check_output="$(wp plugin check /var/www/html/wp-content/plugins/cost-importer-for-woocommerce --require=./wp-content/plugins/plugin-check/cli.php)"
printf '%s\n' "$plugin_check_output"
if printf '%s\n' "$plugin_check_output" | grep -Eq '(^|[[:space:]])ERROR([[:space:]]|$)'; then
  exit 1
fi
wp eval 'if ( ! class_exists("\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil") ) { exit(1); } echo "hpos-declaration-runtime-pass";'
wp eval '$p = new WC_Product_Simple(); $p->set_name("Blue Mug"); $p->set_sku("MUG-BLUE"); $p->save(); $v = new WC_Product_Variation(); $v->set_parent_id($p->get_id()); $v->set_sku("VARIATION-XL"); $v->save(); echo "products-ready";'
wp eval 'if (!class_exists("CIWC_Plugin") || !class_exists("CIWC_CSV") || "12.5" !== CIWC_CSV::parse_cost("12,50")) { exit(1); } echo "plugin-runtime-pass";'
echo 'FRESH_INSTALL_PASS'
