<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Chat\Plugin\Traits\CommandOnly;
use Room11\Jeeves\Chat\PluginCommandEndpoint;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;

class Github implements Plugin
{
    use CommandOnly;

    private $chatClient;
    private $httpClient;
    private $pluginData;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
    }

    public function github(Command $command): \Generator {
        $obj = $command->getParameter(0) ?? 'status';
        if ($obj === 'status') {
            yield from $this->status($command);
        } elseif (strpos($obj, '/') === false) {
            yield from $this->profile($command, $obj);
        } elseif (strpos($obj, '/') === strrpos($obj, '/')) {
            yield from $this->repo($command, $obj);
        } else {
            return $this->chatClient->postMessage($command->getRoom(),
              "Usage: !!github [status | <project> | <profile> | <profile>/<repo> ]");
        }
    }

    /**
     * Example:
     *   !!github
     *   !!github status
     *
     * [tag:github-status] good: Everything operating normally. as of 2016-05-25T18:44:58Z
     */
    protected function status(Command $command): \Generator {
        $response = yield $this->httpClient->request('https://status.github.com/api/last-message.json');
        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), "Failed fetching status");
        }
        $json = json_decode($response->getBody());
        yield $this->chatClient->postMessage(
            $command->getRoom(),
            sprintf(
                "[tag:github-status] %s: %s as of %s",
                $json->status,
                $json->body,
                $json->created_on
            )
        );
    }

    /**
     * Eample:
     *   !!github Room-11
     *
     * [tag:github-profile] Organization [Room-11](https://github.com/Room-11): 15 public repos
     */
    protected function profile(Command $command, $profile): \Generator {
        $response = yield $this->httpClient->request('https://api.github.com/users/'.urlencode($profile));
        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), "Failed fetching profile for $profile");
        }
        $json = json_decode($response->getBody());
        if (!isset($json->id)) {
            return $this->chatClient->postMessage($command->getRoom(), "Unknown profile $profile");
        }
        yield $this->chatClient->postMessage(
            $command->getRoom(),
            sprintf(
                "[tag:github-profile] %s [%s](%s): %d public repos",
                $json->type,
                $json->name,
                $json->html_url,
                $json->public_repos
            )
        );
    }

    /**
     * Example:
     *   !!github Room-11/Jeeves
     *
     * [tag:github-repo] [Room-11/Jeeves](https://github.com/Room-11/Jeeves) Chatbot attempt -
     *    - Watchers: 14, Forks: 15, Last Pushed: 2016-05-26T08:57:41Z
     */
    protected function repo(Command $command, $path): \Generator {
        list($user, $repo) = explode('/', $path, 2);
        $response = yield $this->httpClient->request('https://api.github.com/repos/' . urlencode($user).'/'.urlencode($repo));
        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), "Failed fetching repo for $path");
        }
        $json = json_decode($response->getBody());
        if (!isset($json->id)) {
            return $this->chatClient->postMessage($command->getRoom(), "Unknown repo $path");
        }
        yield $this->chatClient->postMessage(
            $command->getRoom(),
            sprintf(
                "[tag:github-repo] [%s](%s) %s - Watchers: %d, Forks: %d, Last Push: %s",
                $json->full_name,
                $json->html_url,
                $json->description,
                $json->watchers,
                $json->forks,
                $json->pushed_at
            )
        );
    }

    public function getName(): string
    {
        return 'Github.php';
    }

    public function getDescription(): string
    {
        return 'Displays Github status, profile, or repo information';
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
        return [
            new PluginCommandEndpoint('Github', [$this, 'github'], 'github',
                                      'Display Github status, profile, or repo info'),
        ];
    }
}