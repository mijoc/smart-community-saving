# Project image bundle

This folder contains a portable copy of the images currently included in the
project so they are included when the project is downloaded and uploaded to
cPanel.

The project also includes the application image folders at
`public/images/`. The application checks that public location first, so a
complete project upload already includes the current images.

## Restore Laravel uploaded images on cPanel

If you prefer Laravel's normal storage link instead of `public/images`, copy
the application image folders from this bundle into Laravel's public storage
directory:

```bash
cp -a images/avatars images/members images/payment-proofs images/system storage/app/public/
rm -f public/storage
php artisan storage:link
```

The `references` folder contains uploaded reference screenshots and is not
used by the application. The `icons` folder contains the app icons used by
the PWA.

After copying the files, make sure the domain document root points to the
project's `public` directory and clear the Laravel cache:

```bash
php artisan optimize:clear
php artisan config:cache
```

Do not upload the image bundle only to an unrelated `public_html/images`
directory. It must be inside the Laravel application's `public/images`
directory, or inside `storage/app/public` with the `public/storage` link.