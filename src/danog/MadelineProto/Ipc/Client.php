<?php
/**
 * API wrapper module.
 *
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2020 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto\Ipc;

use Amp\Deferred;
use Amp\Ipc\Sync\ChannelledSocket;
use danog\MadelineProto\API;
use danog\MadelineProto\Exception;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Tools;

/**
 * IPC client.
 */
class Client
{
    use \danog\MadelineProto\Wrappers\Start;
    use \danog\MadelineProto\Wrappers\Templates;

    /**
     * IPC server socket.
     */
    private ChannelledSocket $server;
    /**
     * Requests promise array.
     */
    private array $requests = [];
    /**
     * Logger instance.
     */
    public Logger $logger;
    /**
     * Constructor function.
     *
     * @param ChannelledSocket $socket IPC client socket
     * @param Logger           $logger Logger
     */
    public function __construct(ChannelledSocket $server, Logger $logger)
    {
        $this->logger = $logger;
        $this->server = $server;
        Tools::callFork($this->loop());
    }
    /**
     * Logger.
     *
     * @param string $param Parameter
     * @param int    $level Logging level
     * @param string $file  File where the message originated
     *
     * @return void
     */
    public function logger($param, int $level = Logger::NOTICE, string $file = ''): void
    {
        if ($file === null) {
            $file = \basename(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'], '.php');
        }
        isset($this->logger) ? $this->logger->logger($param, $level, $file) : Logger::$default->logger($param, $level, $file);
    }
    /**
     * Main loop.
     *
     * @return \Generator
     */
    private function loop(): \Generator
    {
        while ($payload = yield $this->server->receive()) {
            [$id, $payload] = $payload;
            if (!isset($this->requests[$id])) {
                Logger::log("Got response for non-existing ID $id!");
            } else {
                $promise = $this->requests[$id];
                unset($this->requests[$id]);
                if ($payload instanceof ExitFailure) {
                    $promise->fail($payload->getException());
                } else {
                    $promise->resolve($payload);
                }
                unset($promise);
            }
        }
    }
    /**
     * Unreference.
     *
     * @return void
     */
    public function unreference(): void
    {
        if (isset($this->server)) {
            Tools::wait($this->server->disconnect());
        }
    }
    /**
     * Call function.
     *
     * @param string $function  Function name
     * @param array  $arguments Arguments
     *
     * @return \Generator
     */
    public function __call(string $function, array $arguments): \Generator
    {
        $this->requests []= $deferred = new Deferred;
        yield $this->server->send([$function, $arguments]);
        return yield $deferred->promise();
    }
    /**
     * Placeholder.
     *
     * @param mixed ...$params Params
     *
     * @return void
     */
    public function setEventHandler(...$params): void
    {
        throw new Exception("Can't use ".__FUNCTION__." in an IPC client instance, please use a full ".API::class." instance, instead!");
    }
    /**
     * Placeholder.
     *
     * @param mixed ...$params Params
     *
     * @return void
     */
    public function getEventHandler(...$params): void
    {
        throw new Exception("Can't use ".__FUNCTION__." in an IPC client instance, please use a full ".API::class." instance, instead!");
    }
}
