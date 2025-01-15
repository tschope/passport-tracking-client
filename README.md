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
    'application_id' => '40066000000',
	'estimated_issue_date' => '11/02/2025',
	'last_update' => '14/01/2025',
	'progress' => 3.24, // percentage
	'application_received' => '08/01/2025',
	'alert_date' => '14/01/2025',
	'alert_title' => 'Processing application',
	'alert_message' => "We have received your supporting documents. We are now verifying these documents.",
	'status_history' => 
	[
        'date' => '15/11/2024',
        'status' => 'Dispatched',
        'message' => 'Your Passport Book was posted on 15/11/2024',
    ],
    [
        'date' => '15/11/2024',
        'status' => 'Printing',
        'message' => 'Your Passport Card is being printed',
    ],
)
```

## Contributing

Contributions are welcome! Please submit a pull request or open an issue to discuss any changes.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

