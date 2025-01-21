# Changelog

## [1.3.4]
### Fixed
- Fixed an error received when an order contains a product that has been deleted

### Improved
- Added the possibility to recreate script via Javascript function

## [1.3.3]
### Fixed
- Fixed error when trying print PDF invoices

## [1.3.2]
### Fixed
- Added hook name in shipping method custom title tip text
- Fixed in the Checkout page to show only active terminals

## [1.3.1]
### Improved
- Added the possibility to specify the custom title of the delivery method for each country
- Added some more Lithuanian translations

## [1.3.0]
### Improved
- Reworked location retrieval method by first downloading locations to a temporary DB table and only after successful download, copying them to the main DB table
- Created detailed display of steps for updating delivery locations
- Added part of translations into Lithuanian language

### Updated
- API library to v1.0.6

## [1.2.5]
### Improved
- Improved for faster loading of terminals on the front, only the nearest terminals within 50 km from the entered address are displayed
- Changed tracking URL from HRX to partners

## [1.2.4]
### Fixed
- Fixed PHP warning in courier locations update function
- Fixed memory leak when updating locations

### Improved
- Added a abiblity to manualy launch cronjob
- Added delivery locations update logging when debug mode is enabled
- Changed getting locations from 250 to 10000 per request
- Changed request timeout time from 40 to 60 seconds
- Added changelog file to plugin directory

## [1.2.3] - 2023-10-18
### Fixed
- Fixed error message display when shipping method countries list is empty

### Improved
- Added the option to write the company name on the label
- Improved to avoid saving the same information multiple times when editing an order
- Added parameter to activate automatically mark HRX order as ready when WC order status is changed to "Completed"

### Updated
- API library to v1.0.5

## [1.2.2.1] - 2023-10-04
### Fixed
- Fixed a bug when getting WC Order status

## [1.2.2] - 2023-09-25
### Fixed
- Fixed a bug which stopping receive of delivery locations
- Fixed a bug that caused admin functions to activate in the front page
- Fixed "HRX delivery" menu item counter when entered to Order edit page
- Fixed HRX metadata adding when in Order selected not HRX shipping method
- Fixed meta_query when getting HRX Orders list

### Improved
- Created a "free delivery from the cart amount" option for every country
- Added price block hiding when country is disabled

### Updated
- API library to v1.0.4

## [1.2.1] - 2023-08-28
### Improved
- Made it possible to use random time when registering cronjob

## [1.2.0.1] - 2023-08-21
### Fixed
- Fixed the units conversion
- Fixed error when plugin installing first time

### Improved
- Added a more informative message about locations being updated

## [1.2.0] - 2023-08-08
### Improved
- The plugin is adapted to work with Woocommerce HPOS (prepared for Woocommerce 8)

## [1.1.1] - 2023-07-20
### Improved
- Changed get of delivery countries from API instead of code
- Added option to select shipping method title type on Checkout page
- Changed delivery locations auto update from monthly to weekly
- Changed warehouse locations auto update from daily to weekly
- Added a option to change WC order status when HRX order is (un)marked as "Ready"

### Updated
- API library to v1.0.3
- TerminalMapping library to v1.2.3

## [1.1.0.1] - 2023-07-07
### Fixed
- Fixed delivery locations Update button action

## [1.1.0] - 2023-06-12
### Improved
- Added a option to choose how much orders show in HRX Orders list
- Disabled mass action checkbox for orders, which have error
- Added Woocommerce Order preview ability in HRX delivery page
- Disabled new shipment registration when WC order status is Cancelled, Refunded or Failed
- Added mass buttons for all Order actions

## [1.0.1] - 2022-12-09
### Improved
- Added marker logo display by country

## [1.0.0] - 2022-11-24
- Initial release
