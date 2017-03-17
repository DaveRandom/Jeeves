<?php declare(strict_types=1);
namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Amp\repeat;
use function Room11\DOMUtils\domdocument_load_html;

class PHPBugs extends BasePlugin
{
    private const RECENT_BUGS = "https://bugs.php.net/search.php?search_for=&boolean=0&limit=30&order_by=id&direction=DESC&cmd=display&status=All&bug_type=All&project=PHP&php_os=&phpver=&cve_id=&assign=&author_email=&bug_age=0&bug_updated=0";

    private $chatClient;
    private $httpClient;
    private $pluginData;
    private $rooms;
    private $lastKnownBugId = null;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
        $this->rooms = [];

        repeat(
            function () {
                return $this->checkBugs();
            }, 300000
        );
    }

    public function enableForRoom(ChatRoom $room, bool $persist = true)
    {
        $this->rooms[$room->getIdentifier()->getIdentString()] = $room;
    }

    private function checkBugs()
    {
        $bugs = yield from $this->getRecentBugs();

        if (!$bugs) {
            return null;
        }

        if ($this->lastKnownBugId === null) {
            $this->lastKnownBugId = $bugs[0]["id"];
            return null;
        }

        $lastId = $this->lastKnownBugId;
        $this->lastKnownBugId = $bugs[0]["id"];

        foreach ($bugs as $bug) {
            if ($bug["id"] <= $lastId) {
                return null;
            }

            foreach ($this->rooms as $room) {
                yield $this->chatClient->postMessage($room, \sprintf(
                    "[tag:bug] #%d: %s – %s",
                    $bug["id"],
                    $bug["title"],
                    $bug["url"]
                ));
            }
        }

        return null;
    }

    private function getRecentBugs()
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(self::RECENT_BUGS);

        if ($response->getStatus() !== 200) {
            return false;
        }

        /** @var \DOMElement $table */
        $table = domdocument_load_html($response->getBody())
            ->getElementById("top")
            ->nextSibling;

        /** @var \DOMElement $content */
        $content = $table->getElementsByTagName("td")->item(0);
        $rows = $content->getElementsByTagName("tr");

        $bugs = [];

        foreach ($rows as $row) {
            /** @var \DOMElement $row */
            if (!$row->hasAttribute("valign")) {
                continue;
            }

            $cells = $row->getElementsByTagName("td");
            $id = (int) $cells->item(0)->firstChild->textContent;

            $bugs[] = [
                "id" => $id,
                "name" => $cells->item(8)->textContent ?: "*none*",
                "url" => "https://bugs.php.net/{$id}",
            ];
        }

        return $bugs;
    }

    public function getDescription(): string
    {
        return 'Pushes new PHP.net bugs';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [];
    }
}
