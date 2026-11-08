# Takar

Takar is a self-hosted kasir (POS), stock and restock-advice web app for one small shop.

## Run

```
docker compose up -d
```

Ports 80 and 443 are used by default. If they are taken, set `HTTP_PORT` and `HTTPS_PORT`
(for example `HTTP_PORT=8080 HTTPS_PORT=8443 docker compose up -d`). Set `SITE_ADDRESS` to your
domain for a real certificate; the default is `localhost`.

Set the Owner password (the first sign-in has no password until you do):

```
docker compose exec -it app php artisan takar:reset-owner-password
```

Then open https://localhost (add `:8443` if you changed `HTTPS_PORT`) and sign in with username `owner`.

## Tests

```
docker compose run --rm --build test
```
