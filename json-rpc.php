<?php

namespace App\Controller;

use Throwable;
use DI\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use App\Error\ParseError;
use App\Error\NotFound;
use App\Error\InvalidRequest;
use App\Error\InvalidParams;
use App\Error\ServerError;
use App\Error\Error;

class Api extends Controller
{
    /**
     * Methods index
     *
     * @var array
     */
    protected $index;

    /**
     * JSON RPC API controller construct
     *
     * @param DI\Container $container
     */
    function __construct(Container $container)
    {
        parent::__construct($container);

        // index api methods by name
        $procedures = $this->container->get("app.procedures");
        $this->index = array_column($procedures, null, "method");
    }

    /**
     * Process JSON-RPC 2.0 request
     *
     * @param \Psr\Http\Message\RequestInterface $request
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    function json_rpc_2(RequestInterface $request): ResponseInterface
    {
        $response = [];
        $process = $this->parse_request($request);

        if (!isset($process[0])) {
            $process = [ $process ];
            $is_batch = false;
        } else {
            $is_batch = true;
        }

        foreach ($process as $object) {
            try {
                $this->validate_request($object);
                $this->validate_params($object);

                $result = $this->success_response(
                    $this->proc_call(
                        $object["method"],
                        $object["params"] ?? []
                    ),
                    $object["id"] ?? null,
                    isset($object["id"])
                );
            } catch (Throwable $error) {
                $result = $this->error_response(
                    $error,
                    $object["id"] ?? null,
                    $error instanceof InvalidRequest
                    || isset($object["id"])
                );
            }

            if (isset($result)) {
                $response[] = $result;
            }
        }

        if (!$is_batch) {
            $response = array_shift($response);
        }

        if (!empty($response)) {
            $response = json_encode($response);
        } else {
            $response = '';
        }

        $this->response = $this->response
            ->withStatus(200)
            ->withBody(
                $this->container
                    ->get(StreamFactoryInterface::class)
                    ->createStream($response)
            );

        return $this->response;
    }

    /**
     * Create JSON RPC success response
     *
     * @param mixed $result result
     * @param int $id request id
     * @param bool $notify is not a notification
     *
     * @return array|null
     */
    protected function success_response($result, ?int $id, bool $notify): ?array
    {
        if (!$notify) {
            return null;
        }

        return [
            "jsonrpc" => "2.0",
            "result" => $result,
            "id" => $id
        ];
    }

    /**
     * Create JSON RPC error response based on thrown Exception
     *
     * @param \Throwable $e
     * @param int $id request id
     * @param bool $notify is not a notification
     *
     * @return array|null
     */
    protected function error_response(Throwable $e, ?int $id, bool $notify): ?array
    {
        if (!$notify) {
            return null;
        }

        if (!($e instanceof Error)) {
            $message = $e->getMessage();
            $e = new ServerError($message);
        }

        $response = [
            "jsonrpc" => "2.0",
            "error" => [
                "code" => $e->getJsonRpcCode(),
                "message" => $e->getMessage(),
                "data" => $e->getData()
            ],
            "id" => $id
        ];

        if (empty($response["error"]["data"])) {
            unset($response["error"]["data"]);
        }

        return $response;
    }

    /**
     * Parse JSON request
     *
     * @param \Psr\Http\Message\RequestInterface $request
     *
     * @throws \App\Error\ParseError invalid JSON
     *
     * @return array
     */
    protected function parse_request(RequestInterface $request): array
    {
        try {
            $body = $request
                ->getBody()
                ->getContents(); // throws RuntimeException
        } catch (Throwable $e) {
            throw new ParseError($e->getMessage());
        }

        if (empty($body)) {
            throw new ParseError("Empty message");
        }

        $decoded = json_decode($body, true);
        $message = json_last_error_msg();
        $code = json_last_error();

        if ($code !== JSON_ERROR_NONE) {
            throw new ParseError($message);
        }

        return $decoded;
    }

    /**
     * Checks if single request is valid
     *
     * @param mixed $request decoded JSON request
     *
     * @throws \App\Error\InvalidRequest invalid object
     *
     * @return void
     */
    protected function validate_request($request): void
    {
        if (!is_array($request) || empty($request)) {
            throw new InvalidRequest("Request is not a valid object");
        }

        if (
            !array_key_exists("jsonrpc", $request)
            || $request["jsonrpc"] !== "2.0"
        ) {
            throw new InvalidRequest("Unknown version");
        }

        if (
            !array_key_exists("method", $request)
            || !is_string($request["method"])
        ) {
            throw new InvalidRequest("Invalid request");
        }

        if (
            isset($request["params"])
            && !is_array($request["params"])
        ) {
            throw new InvalidRequest("Invalid request");
        }
    }

    /**
     * Validates params from API request
     *
     * @param array $request valid request
     *
     * @throws \App\Error\NotFound method not exists
     * @throws \App\Error\InvalidParams method params are wrong
     *
     * @return void
     */
    protected function validate_params(array $request): void
    {
        if (!array_key_exists($request["method"], $this->index)) {
            throw new NotFound("Method not found");
        }

        $correct = true;
        $method = $request["method"];
        $expected = $this->index[$method];
        $eparams = $expected["params"] ?? [];
        $params = $request["params"] ?? [];

        if (empty($params) && !empty($eparams)) {
            throw new InvalidParams(
                sprintf(
                    "Expected params: %s",
                    json_encode($eparams)
                )
            );
        }

        if (is_string($eparams)) {
            $eparams = ["*" => $eparams];
            $params = ["*" => $params];
        }

        if (is_array($eparams)) {
            foreach ($eparams as $key => $type) {
                $value = $params[$key] ?? null;
                $correct = $this->validate_param_type($value, $type);

                if (!$correct) {
                    throw new InvalidParams(
                        sprintf(
                            "Unexpected type %s for %s, expected: %s",
                            gettype($value),
                            $key,
                            $type
                        )
                    );
                }
            }
        }
    }

    /**
     * Validates param type using type string
     *
     * @param mixed $value
     * @param string $type
     *
     * @return boolean
     */
    protected function validate_param_type($value, string $type): bool
    {
        if (
            !isset($value)
            && $type[0] === '?'
        ) {
            return true;
        }

        if (substr_compare($type, "[]", -2) === 0) {
            if (is_array($value)) {
                $subtype = substr($type, 0, strlen($type) - 2);

                foreach ($value as $subvalue) {
                    if (!$this->validate_param_type(
                        $subvalue,
                        $subtype
                    )) {
                        return false;
                    }
                }

                return true;
            } else {
                return false;
            }
        }

        switch (ltrim($type, '?')) {
            case "any":
                return true;

            case "array":
                return is_array($value);

            case "bool":
            case "boolean":
                return is_bool($value);

            case "int":
            case "integer":
                return is_int($value);

            case "float":
            case "double":
                return is_float($value);

            case "numeric":
                return is_numeric($value);

            case "str":
            case "string":
                return is_string($value);

            case "alpha":
                return is_string($value) && ctype_alpha($value);

            case "alnum":
                return is_string($value) && ctype_alnum($value);

            case "scalar":
                return is_scalar($value);

            case "null":
                return is_null($value);

            default:
                return false;
        }
    }

    /**
     * Call internal function
     *
     * @param string $name function key
     * @param array $params
     * @param bool $internal is internal call
     *
     * @throws \App\Error\NotFound procedure not found
     * @throws \Throwable on error
     *
     * @return mixed
     */
    function proc_call(string $name, array $params = [], bool $internal = false)
    {
        // find procedure handler
        $route = $this->index[$name] ?? [];
        $handler = $route["handler"] ?? null;

        if (empty($handler)) {
            throw new NotFound("Method not found");
        }

        if (!$internal) {
            // check user access
            $authenticated = $this->container
                ->call(
                    [Auth::class, "authenticated"],
                    ["access" => $route["access"]]
            );

            if (!$authenticated) {
                throw new NotFound("Not found");
            }
        }

        // if (
        //     is_array($handler)
        //     && is_string($handler[0])
        //     && is_subclass_of($handler[0], Controller::class)
        //     && $this->container->has($handler[0])
        // ) {
        //     $handler[0] = $this->container->get($handler[0]);
        // }

        return $this->container->call($handler, $params);
    }

    /**
     * Handle application error
     *
     * @param \Throwable $error
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    function handle_error(Throwable $error): ResponseInterface
    {
        $this->response = $this->response
            ->withStatus(200)
            ->withBody(
                $this->container
                    ->get(StreamFactoryInterface::class)
                    ->createStream(json_encode(
                        $this->error_response(
                            $error,
                            null,
                            true
                        )
                    ))
            );

        return $this->response;
    }
}
