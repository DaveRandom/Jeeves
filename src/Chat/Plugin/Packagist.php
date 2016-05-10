<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class Packagist implements Plugin
{
    use CommandOnly;

    private $chatClient;
    /**
     * @var HttpClient
     */
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResultFromSearchFallback(string $vendor, string $package): \Generator {
        $url = 'https://packagist.org/search/?q=' . urlencode($vendor) . '%2F' . urldecode($package);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($url);

        $dom = domdocument_load_html($response->getBody());
        $nodes = (new \DOMXPath($dom))
            ->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' packages ')]/li");

        if ($nodes->length === 0) {
            throw new \RuntimeException('Search page contains no results');
        }

        /** @var \DOMElement $node */
        $node = $nodes->item(0);
        if (!$node->hasAttribute('data-url')) {
            throw new \RuntimeException('First result has no URL');
        }

        return yield $this->httpClient->request('https://packagist.org' . $node->getAttribute('data-url') . '.json');
    }

    public function search(Command $command): \Generator
    {
        $info = explode('/', implode('/', $command->getParameters()), 2);

        if (count($info) !== 2) {
            yield from $this->chatClient->postReply($command, "Usage: `!!packagist vendor package`");
            return;
        }

        list ($vendor, $package) = $info;

        $url = 'https://packagist.org/packages/' . urlencode($vendor) . '/' . urldecode($package) . '.json';

        try {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($url);

            if ($response->getStatus() !== 200) {
                $response = yield from $this->getResultFromSearchFallback($vendor, $package);
            }

            $data = json_try_decode($response->getBody());

            yield from $this->chatClient->postMessage(
                $command->getRoom(),
                sprintf(
                    "[ [%s](%s) ] %s",
                    $data->package->name,
                    $data->package->repository,
                    $data->package->description
                )
            );
        } catch (\Throwable $e) {
            yield from $this->chatClient->postReply($command, 'No matching packages found');
        }
    }

    public function getName(): string
    {
        return 'Packagist';
    }

    public function getDescription(): string
    {
        return 'Fetches package information from packagist.org';
    }

    public function getHelpText(array $args): string
    {
        // TODO: Implement getHelpText() method.
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'packagist')];
    }
}
