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

class Imdb implements Plugin
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

        if ($dom->getElementsByTagName('resultset')->length === 0) {
            return 'I cannot find that title.';
        }

        /** @var \DOMElement $result */
        $result = $dom->getElementsByTagName('imdbentity')->item(0);
        /** @var \DOMText $titleNode */
        $titleNode = $result->firstChild;

        return sprintf(
            '[ [%s](%s) ] %s',
            $titleNode->wholeText,
            'http://www.imdb.com/title/' . $result->getAttribute('id'),
            $result->getElementsByTagName('description')->item(0)->textContent
        );
    }

    public function search(Command $command): \Generator
    {
        if (!$command->hasParameters()) {
            return;
        }

        $response = yield $this->httpClient->request(
            'http://www.imdb.com/xml/find?xml=1&nr=1&tt=on&q=' . rawurlencode(implode(' ', $command->getParameters()))
        );

        yield from $this->chatClient->postMessage(
            $command->getRoom(),
            $this->getMessage($response)
        );
    }

    public function getName(): string
    {
        return 'IMDB';
    }

    public function getDescription(): string
    {
        return 'Searches and displays IMDB entries';
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
        return [new PluginCommandEndpoint('Search', [$this, 'search'], 'imdb')];
    }
}
