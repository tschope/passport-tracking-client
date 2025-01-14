<?php

namespace Tschope\PassportTrackingClient;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class PassportTrackingClient
{
    private Client $client;
    private string $stepOneUrl = "https://passporttracking.dfa.ie/PassportTracking/";
    private string $stepTwoUrl = "https://passporttracking.dfa.ie/PassportTracking/Home/GetStep";
    private ?string $requestToken = null;

    public function __construct()
    {
        $this->client = new Client([
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Referer' => $this->stepOneUrl,
            ]
        ]);
    }

    public function getStatus(string $reference): array
    {
        $this->fetchRequestToken();
        return $this->fetchStatus($reference);
    }

    private function fetchRequestToken(): void
    {
        $response = $this->client->get($this->stepOneUrl);
        $content = (string) $response->getBody();
        preg_match('/<input.*name="__RequestVerificationToken".*value="([^"]+)"/', $content, $matches);

        if (!isset($matches[1])) {
            throw new \Exception("Request token not found.");
        }

        $this->requestToken = $matches[1];
    }

    private function fetchStatus(string $reference): array
    {
        if (!$this->requestToken) {
            throw new \Exception("Request token is required.");
        }

        try {
            $response = $this->client->post($this->stepTwoUrl, [
                'form_params' => [
                    '__RequestVerificationToken' => $this->requestToken,
                    'search[Criteria][ReferenceNumber]' => $reference,
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $body = (string) $response->getBody();
                $crawler = new Crawler($body);

                // Extrair detalhes
                $applicationId = $this->getApplicationId($crawler);
                $issueDate = $this->getEstimatedIssueDate($crawler);
                $lastUpdated = $this->getLastUpdated($crawler);
                $progressDetails = $this->getProgressDetails($crawler);
                $alertDetails = $this->getAlertDetails($crawler);

                return array_merge($applicationId, $issueDate, $lastUpdated, $progressDetails, $alertDetails);
            }

            return [
                'error' => true,
                'message' => 'Failed to complete step two. Response status: ' . $response->getStatusCode(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ];
        }

    }

    private function getApplicationId(Crawler $crawler): array
    {
        $idContainer = $crawler->filter('div.jumbotron h2');
        if ($idContainer->count() === 0) {
            return [
                'Application Id' => null,
            ];
        }

        return [
            // 'Application Id' => trim($idContainer->text(null)),
            'Application Id' => trim(str_replace('Passport Application ID:', '', $idContainer->text(null))),
        ];
    }

    private function getEstimatedIssueDate(Crawler $crawler): array
    {
        $issueDateContainer = $crawler->filter('div.jumbotron div.status-date');
        if ($issueDateContainer->count() === 0) {
            return [
                'Estimated Issue Date' => null,
            ];
        }

        return [
            'Estimated Issue Date' => trim($issueDateContainer->text(null)),
        ];
    }

    private function getLastUpdated(Crawler $crawler): array
    {
        $lastUpdatedContainer = $crawler->filter('div.jumbotron div.lastUpdated');
        if ($lastUpdatedContainer->count() === 0) {
            return [
                'Last Updated' => null,
            ];
        }

        return [
            'Last Updated' => trim(str_replace('Last Updated:', '', $lastUpdatedContainer->text(null))),
        ];
    }

    private function getProgressDetails(Crawler $crawler): array
    {
        $progressBar = $crawler->filter('div.progress-bar');
        $progressStyle = $progressBar->attr('style') ?? '';
        preg_match('/width:\s*(\d+(\.\d+)?)%/', $progressStyle, $matches);

        $applicationReceived = $crawler->filter('div.progress-tracking-left div.status-date')->text(null);

        return [
            'Progress' => ($matches[1] ?? '0') . '%',
            'Application Received' => trim($applicationReceived),
        ];
    }

    private function getAlertDetails(Crawler $crawler): array
    {
        $alertRow = $crawler->filter('table.table tr')->first();
        if ($alertRow->count() === 0) {
            return [
                'Alert Date' => null,
                'Alert Title' => null,
                'Alert Message' => null,
            ];
        }

        $alertDate = $alertRow->filter('span.vertical-date small')->text(null);
        $alertTitle = $alertRow->filter('h2')->text(null);
        $alertMessage = $alertRow->filter('p')->text(null);

        return [
            'Alert Date' => trim($alertDate),
            'Alert Title' => trim($alertTitle),
            'Alert Message' => trim($alertMessage),
        ];
    }
}
