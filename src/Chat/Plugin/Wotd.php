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

class Wotd implements Plugin
{
    use CommandOnly;

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getMessage(HttpResponse $response): string
    {
        $dom = domdocument_load_html($response->getBody());

        if ($dom->getElementsByTagName('description')->length === 0) {
            return 'I dun goofed';
        }

        preg_match('/([^:]+)/', $dom->getElementsByTagName('description')->item(2)->textContent, $before);
        preg_match('/\:(.*)/', $dom->getElementsByTagName('description')->item(2)->textContent, $after);

        return '**['.$before[0].'](http://www.dictionary.com/browse/'.str_replace(" ", "-", $before[0]).')**' . $after[0];
    }

    public function fetch(Command $command): \Generator
    {
        $response = yield $this->httpClient->request(
            'http://www.dictionary.com/wordoftheday/wotd.rss'
        );

        yield from $this->chatClient->postMessage($command->getRoom(), $this->getMessage($response));
    }

    public function getName(): string
    {
        return 'WOTD';
    }

    public function getDescription(): string
    {
        return 'Gets the Word Of The Day from dictionary.com';
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
        return [new PluginCommandEndpoint('Fetch', [$this, 'fetch'], 'wotd')];
    }
}
