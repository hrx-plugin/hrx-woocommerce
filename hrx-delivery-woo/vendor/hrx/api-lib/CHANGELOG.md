# Changelog

## [1.0.6] - Locations API v2
### Improved
- API v2 has started to be used to update delivery locations
- added getDeliveryLocationsCountries() function to API class
- added getDeliveryLocationsForCountry() function to API class

### Changed
- changed to only use the domain when assigning URL

## [1.0.5] - Repeat request up to 5 times
### Fixed
- increased CURL request repeat times to 5

## [1.0.4] - Repeat request on error
### Improved
- repeat CURL request when got CURL error "Connection reset by peer" (56)

## [1.0.3] - Better API class control
### Improved
- increased timeout time to 15 sec
- added setTimeout() function to API class
- improved API class debug control and created debug data output
- added setTestMode() function to API class
- added setToken() function to API class
- added setTestUrl() function to API class
- added setLiveUrl() function to API class

## [1.0.2] - Courier shipping
### Improved
- added a function to change order ready state
- added shipping via courier

## [1.0.1] - Order cancel actions
### Improved
- added a function to cancel order
- added getting of return label

## [1.0.0] - Initial release
