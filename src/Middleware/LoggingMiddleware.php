<?php
namespace BUR\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LoggingMiddleware
{
    /**
     * @var string
     */
    private $requestId;

    /**
     * @var string
     */
    protected $header = 'x-request-id';

    /**
     * Generate a requestId for this request
     *
     * @return string
     */
    public function getGenerateRequestId()
    {

        $appName = str_slug(config('app.name'));
        $appEnv = str_slug(config('app.env'));

        $prefix = $appName . '/' . $appEnv. '/';


        if ($this->requestId === null) {
            $this->requestId = uniqid($prefix, true);
        }

        return $this->requestId;
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     * @throws \Exception
     */
    protected function getRequestLogInfo(Request $request)
    {

        return [
          'environment'     => config('app.env'),
          'requestQuery'    => $request->getPathInfo(),
          'requestHeaders'  => $request->headers->all(),
          'requestParams'   => $request->attributes->all(),
          'requestHostname' => $request->getHttpHost(),
          'ip'              => $request->getClientIp(),
          'userAgent'       => $request->userAgent(),
          'contentType'     => $request->getContentType(),
          'requestId'       => $this->getRequestId($request),
          'requestMethod'   => $request->getMethod()


        ];
    }


    protected function getResponseLogInfo(Response $response, Request $request)
    {

        $requestId = $this->getGenerateRequestId();

        // add the requestId to the response, so we can use it later again
        $response->requestId = $requestId;

        $responseLog =  [
          'clientStatus'          => $response->getStatusCode(),
          'clientResponseHeaders' => $response->headers->all(),
          'clientRequestBody'     => $request->getContent(),
          'clientRequestHeaders'  => $request->headers->all(),
          'clientResponseBody'    => $response->getContent(),
          'timeSinceStart'        => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 5),
          'requestId'             => $requestId
        ];

        if ($response->isNotFound())  //Exclude logging whole response Bodies for 404s because the entire Html will be logged.
        {
            $responseLog['clientResponseBody'] = "<>";
        } else {
            $responseLog['clientResponseBody'] = $response->getContent();
        }

        return $responseLog;
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|string
     * @throws \Exception
     */
    protected function getRequestId(Request $request)
    {

        $requestId = $this->getGenerateRequestId();

        // add the request id to the request, so we can use it also in the controller
        $request->requestId = $requestId;

        return $request->header($this->header, $requestId);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        Log::debug(
          'Request received',
          $this->getRequestLogInfo($request)
        );

        /**
         * @var \Illuminate\Http\Response
         */
        $response = $next($request);

        $response->header($this->header, $this->getGenerateRequestId());

        Log::debug(
          'Request handled',
          $this->getResponseLogInfo($response, $request)
        );

        return $response;
    }
}
