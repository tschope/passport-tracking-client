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

            $defaultResponse = [
                'error' => false,
                'message' => null,
            ];

            if ($response->getStatusCode() === 200) {
                $body = (string) $response->getBody();
                $crawler = new Crawler($body);

                if ($crawler->filter('div.alert.alert-danger')->count() > 0) {
                    $errorMessage = $crawler->filter('div.alert.alert-danger')->text(null);
                    return [
                        'error' => true,
                        'message' => trim($errorMessage),
                    ];
                }

                // Extrair detalhes
                $applicationId = $this->getApplicationId($crawler);
                $issueDate = $this->getEstimatedIssueDate($crawler);
                $lastUpdated = $this->getLastUpdated($crawler);
                $progressDetails = $this->getProgressDetails($crawler);
                $alertDetails = $this->getAlertDetails($crawler);
                $statusHistory = $this->getStatusHistory($crawler);

                return array_merge(
                    $defaultResponse,
                    $applicationId,
                    $issueDate,
                    $lastUpdated,
                    $progressDetails,
                    $alertDetails,
                    ['status_history' => $statusHistory],
                );
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
                'application_id' => null,
            ];
        }

        return [
            'application_id' => trim(str_replace('Passport Application ID:', '', $idContainer->text(null))),
        ];
    }

    private function getEstimatedIssueDate(Crawler $crawler): array
    {
        $issueDateContainer = $crawler->filter('div.jumbotron div.status-date');
        if ($issueDateContainer->count() === 0) {
            return [
                'estimated_issue_date' => null,
            ];
        }

        return [
            'estimated_issue_date' => trim($issueDateContainer->text(null)),
        ];
    }

    private function getLastUpdated(Crawler $crawler): array
    {
        $lastUpdatedContainer = $crawler->filter('div.jumbotron div.lastUpdated');
        if ($lastUpdatedContainer->count() === 0) {
            return [
                'last_update' => null,
            ];
        }

        return [
            'last_update' => trim(str_replace('Last Updated:', '', $lastUpdatedContainer->text(null))),
        ];
    }

    private function getProgressDetails(Crawler $crawler): array
    {
        $progressBar = $crawler->filter('div.progress-bar');
        $progressStyle = $progressBar->attr('style') ?? '';
        preg_match('/width:\s*(\d+(\.\d+)?)%/', $progressStyle, $matches);

        $applicationReceived = $crawler->filter('div.progress-tracking-left div.status-date')->text(null);

        return [
            'progress' => floatval(($matches[1] ?? '0')),
            'application_received' => trim($applicationReceived),
        ];
    }

    private function getAlertDetails(Crawler $crawler): array
    {
        $alertRow = $crawler->filter('table.table tr')->first();
        if ($alertRow->count() === 0) {
            return [
                'alert_date' => null,
                'alert_title' => null,
                'alert_message' => null,
            ];
        }

        $alertDate = $alertRow->filter('span.vertical-date small')->text(null);
        $alertTitle = $alertRow->filter('h2')->text(null);
        $alertMessage = $alertRow->text(null);

        // Ajustar links de rastreamento no texto de descrição
        $alertMessage = preg_replace_callback(
            '/\b(LG\d{9}IE)\b/',
            function ($matches) {
                $trackingCode = $matches[1];
                $link = "https://www.anpost.com/Post-Parcels/Track/History?item={$trackingCode}";
                return "<a href='{$link}' target='_blank'>{$trackingCode}</a>";
            },
            $alertMessage
        );

        return [
            'alert_date' => trim($alertDate),
            'alert_title' => trim($alertTitle),
            'alert_message' => trim($alertMessage),
        ];
    }

    private function getStatusHistory(Crawler $crawler): array
    {
        $statusHistory = [];

        // Localizar as linhas do histórico de status
        $crawler->filter('div.status-history table.table tbody tr')->each(function (Crawler $row) use (&$statusHistory) {
            $dateText = $row->filter('td span.vertical-date small')->text('');
            $status = $row->filter('td h2')->text('');
            $message = $row->filter('td p')->text('');

            $statusHistory[] = [
                'date' => $dateText,
                'status' => $status,
                'message' => $message,
            ];
        });

        return $statusHistory;
    }

    private function adjustTrackingUrls(Crawler $crawler): array
    {
        $updatedLinks = [];

        // Localizar todas as tags <a> com o link de rastreamento
        $crawler->filter('a')->each(function (Crawler $link) use (&$updatedLinks) {
            $href = $link->attr('href');
            $trackingCode = $link->text(null);

            // Verificar se é o link da An Post e possui um código de rastreamento
            if (strpos($href, 'track.anpost.ie') !== false && !empty($trackingCode)) {
                // Construir a nova URL
                $newHref = "https://www.anpost.com/Post-Parcels/Track/History?item={$trackingCode}";

                // Atualizar o array com as URLs modificadas
                $updatedLinks[] = [
                    'original_href' => $href,
                    'new_href' => $newHref,
                    'tracking_code' => $trackingCode,
                ];
            }
        });

        return $updatedLinks;
    }
}
