<?php

namespace App\Controller;

use App\TwitchConfig;
use App\TwitchHelper;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Views\Twig;

/**
 * About page
 */
class AboutController
{

    /**
     * @var Twig
     */
    private $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function about(Request $request, Response $response, array $args)
    {
        $bins = [];

        $bins['ffmpeg'] = [];
        $bins['ffmpeg']['path'] = TwitchHelper::path_ffmpeg();
        if (file_exists(TwitchHelper::path_ffmpeg())) {
            $out = shell_exec(TwitchHelper::path_ffmpeg() . " -version");
            $out = explode("\n", $out)[0];
            $bins['ffmpeg']['status'] = $out;
        } else {
            $bins['ffmpeg']['status'] = 'Not installed.';
        }

        $bins['mediainfo'] = [];
        $bins['mediainfo']['path'] = TwitchHelper::path_mediainfo();
        if (file_exists(TwitchHelper::path_mediainfo())) {
            $out = shell_exec(TwitchHelper::path_mediainfo() . " --Version");
            $out = explode("\n", $out)[1];
            $bins['mediainfo']['status'] = $out;
        } else {
            $bins['mediainfo']['status'] = 'Not installed.';
        }


        $bins['tcd'] = [];
        $bins['tcd']['path'] = TwitchHelper::path_tcd();
        if (file_exists(TwitchHelper::path_tcd())) {
            $out = shell_exec(TwitchHelper::path_tcd() . " --version");
            $bins['tcd']['status'] = $out;
        } else {
            $bins['tcd']['status'] = 'Not installed.';
        }


        $bins['streamlink'] = [];
        $bins['streamlink']['path'] = TwitchHelper::path_streamlink();
        if (file_exists(TwitchHelper::path_streamlink())) {
            $out = shell_exec(TwitchHelper::path_streamlink() . " --version");
            $bins['streamlink']['status'] = trim($out);
        } else {
            $bins['streamlink']['status'] = 'Not installed.';
        }


        $bins['youtubedl'] = [];
        $bins['youtubedl']['path'] = TwitchHelper::path_youtubedl();
        if (file_exists(TwitchHelper::path_youtubedl())) {
            $out = shell_exec(TwitchHelper::path_youtubedl() . " --version");
            $bins['youtubedl']['status'] = trim($out);
        } else {
            $bins['youtubedl']['status'] = 'Not installed.';
        }


        $bins['pipenv'] = [];
        $bins['pipenv']['path'] = TwitchHelper::path_pipenv();
        if (file_exists(TwitchHelper::path_pipenv())) {
            $out = shell_exec(TwitchHelper::path_pipenv() . " --version");
            $bins['pipenv']['status'] = trim($out);
        } else {
            $bins['pipenv']['status'] = 'Not installed';
        }
        $bins['pipenv']['status'] .= TwitchConfig::cfg('pipenv') ? ', <em>enabled</em>.' : ', <em>not enabled</em>.';


        $bins['python'] = [];
        $out = shell_exec("python --version");
        $bins['python']['version'] = trim($out);

        $bins['python3'] = [];
        $out = shell_exec("python3 --version");
        $bins['python3']['version'] = trim($out);

        $bins['php'] = [];
        $bins['php']['version'] = phpversion();
        $bins['php']['platform'] = PHP_OS;

        return $this->twig->render($response, 'about.twig', [
            'bins' => $bins,
        ]);

    }
}