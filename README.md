# Passport Tracking Client

A PHP package for tracking Irish passport applications using the Irish Passport Tracking Service.

## Installation

Install the package via Composer:

```bash
composer require tschope/passport-tracking-client
```

## Usage

### Initialize the Client

```php
use Tschope\PassportTrackingClient\PassportTrackingClient;

$client = new PassportTrackingClient();
```

### Fetch Status

To fetch the status of a passport application, use the `fetchStatus` method:

```php
$status = $client->fetchStatus('YOUR_APPLICATION_REFERENCE');

print_r($status);
```

Example output:

```
Array
(
    ["Application Id"] => "08/01/2025"
    ["Application Received"] => "08/01/2025
    ["Progress"] => "3.24%"
    ["Estimated Issue Date"] => "11/02/2025"
    ["Alert Date"] => "14/01/2025"
    ["Alert Title"] => "Processing application"
    ["Alert Message"] => "We have received your supporting documents. We are now verifying these documents."
)
```

## Testing

Run tests using PHPUnit:

```bash
composer test
```

## Contributing

Contributions are welcome! Please submit a pull request or open an issue to discuss any changes.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

