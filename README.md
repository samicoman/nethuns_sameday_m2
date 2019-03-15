# Sameday Courier Romania - Magento 2.3 Module

## Current Features - v. 1.0.0
- get price quote from the API (checkout)
- choose between Sameday and Nextday delivery - if applicable (checkout)
- change the shipping origin (admin)
- change the package type (admin)
- add service taxes by default (admin)
- create shipping label using the Sameday API

## Future Features

### v. 1.1.0
- ro_RO translations
- automatically generate AWB on order placed (admin setting)
- cancel AWB for order in admin (/api/awb/ ..)
- add sandbox configuration
- change insured value (admin)

### v. 1.2.0
- status AWB in admin
- optional service taxes on checkout (deschidere colet, reambalare, colet la schimb, retur documente)
- handle multiple parcels

### v. 1.3.0
- delivery hour interval on checkout
- third party pickup config & api
- estimate delivery timeframe on checkout
- lockers (pickup points?)

### Development TODO list
- write tests for API & Carrier
- mock curl OR replace with HTTPClient
- add uninstall script (to remove configs from db)
- add default config values