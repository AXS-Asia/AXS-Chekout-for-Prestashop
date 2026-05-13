# AXS Checkout Installation

## Package Format

Distribute this module as a zip file whose root folder is `axs_checkout`.

The zip must contain:

```text
axs_checkout/
  axs_checkout.php
  composer.json
  payment_link_generator.php
  controllers/
  vendor/
```

Do not zip the files directly without the `axs_checkout` folder.

## Installation Steps

1. Compress the `axs_checkout` folder into `axs_checkout.zip`.
2. Log in to the PrestaShop admin panel.
3. Go to `Modules` > `Module Manager`.
4. Click `Upload a module`.
5. Upload `axs_checkout.zip`.
6. Wait for PrestaShop to install the module.
7. Find `AXS Checkout` in the module list and click `Configure`.

## Alternative Manual Installation

If you install manually on the server:

1. Copy the `axs_checkout` folder into the PrestaShop `modules/` directory.
2. The final path should be `modules/axs_checkout/axs_checkout.php`.
3. Open the PrestaShop admin panel.
4. Go to `Modules` > `Module Manager`.
5. Search for `AXS Checkout` and click `Install`.

## Required Configuration

After installation, configure these fields in the module settings:

- `Enable AXS Checkout`
- `Test mode`
- `Test Payment Link`
- `Test Client ID`
- `Test Secret`
- `Live Payment Link`
- `Live Client ID`
- `Live Secret`

Use test credentials when `Test mode` is enabled.
Use live credentials when `Test mode` is disabled.

## Notes

- The bundled `vendor/` directory is required. Do not remove it from the zip package.
- No additional Composer install step is required on the PrestaShop server if the packaged module already includes `vendor/`.
- This module only offers AXS Checkout for `SGD` orders.