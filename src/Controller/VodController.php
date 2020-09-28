<?php

namespace App\Controller;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

use App\TwitchAutomator;
use App\TwitchConfig;
use App\TwitchHelper;
use App\TwitchVOD;

class VodController
{

    /**
     * Cut up the vod
     *
     * @return void
     */
    public function cut( Request $request, Response $response, $args ) {
        
        set_time_limit(0);

        $TwitchAutomator = new TwitchAutomator();

        if( isset( $_POST['vod'] ) ){

            $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_POST['vod']);

            $username = explode("_", $vod)[0];

            $json = json_decode(file_get_contents(TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json'), true);

            $second_start   = (int)$_POST['start'];
            $second_end     = (int)$_POST['end'];

            if( !$second_start || $second_start > $second_end  ){
                $response->getBody()->write("Invalid start time (" . $second_start . ")");
                return $response;
            }

            if( !$second_end || $second_end < $second_start  ){
                $response->getBody()->write("Invalid end time (" . $second_end . ")");
                return $response;
            }


            $filename_in = TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.mp4';
            $filename_out = TwitchHelper::$public_folder . DIRECTORY_SEPARATOR . "saved_clips" . DIRECTORY_SEPARATOR . $vod . '-cut-' . $second_start . '-' . $second_end . '.mp4';

            if( file_exists($filename_out) ){
                $response->getBody()->write("Output file already exists");
                return $response;
            }

            $cmd = TwitchConfig::cfg('ffmpeg_path');
            $cmd .= ' -i ' . escapeshellarg($filename_in); // input file
            $cmd .= ' -ss ' . escapeshellarg($second_start); // start timestamp
            $cmd .= ' -t ' . escapeshellarg($second_end - $second_start); // length
            $cmd .= ' -codec copy'; // remux
            $cmd .= ' ' . escapeshellarg($filename_out); // output file
            $cmd .= ' 2>&1'; // console output

            $response->getBody()->write( $cmd );

            $output = shell_exec($cmd);

            file_put_contents( __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . "ffmpeg_" . $vod . "-cut-" . $second_start . "-" . $second_end . "_" . time() . ".log", "$ " . $cmd . "\n" . $output);

            $response->getBody()->write("<pre>" . $output . "</pre>");

            $response->getBody()->write("Done");

        }else{

            $response->getBody()->write("No VOD supplied");

        }

        return $response;

    }

    public function chat( Request $request, Response $response, $args ) {
        
        set_time_limit(0);

        $TwitchAutomator = new TwitchAutomator();

        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json');

        if( $vodclass->twitch_vod_id ){
            $response->getBody()->write("Downloading");
            var_dump( $vodclass->downloadChat() );
        }else{
            $response->getBody()->write("VOD has no id");
        }

        return $response;

    }

    public function render_chat( Request $request, Response $response, $args ) {
        
        set_time_limit(0);

        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json');

        $use_vod = isset( $_GET['use_vod'] );

        if( $vodclass->is_chat_downloaded ){
            $response->getBody()->write("Rendering");
            if( $vodclass->renderChat() ){
                $vodclass->burnChat( 300, $use_vod );
            }
        }else{
            $response->getBody()->write("VOD has no chat downloaded");
        }

        return $response;

    }

    public function fullburn( Request $request, Response $response, $args ) {
        
        set_time_limit(0);

        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json');

        if( $vodclass->is_chat_burned ){
            $response->getBody()->write("Chat already burned!");
            return $response;
        }

        $is_muted = $vodclass->checkMutedVod();

        // download chat if not downloaded
        if( !$vodclass->is_chat_downloaded ){
            $vodclass->downloadChat();
            $response->getBody()->write("Chat downloaded<br>");
        }

        if( !$vodclass->is_chat_downloaded ){
            $response->getBody()->write("Chat doesn't exist!");
            return $response;
        }

        if( $is_muted ){ // if vod is muted, use captured one

            if( $vodclass->renderChat() ){
                $vodclass->burnChat();
                $response->getBody()->write("Chat rendered and burned<br>");
            }

        }else{ // if vod is not muted, use it

            // download vod if not downloaded already
            if( !$vodclass->is_vod_downloaded ){
                $vodclass->downloadVod();
                $response->getBody()->write("VOD downloaded<br>");
            }

            // render and burn
            if( $vodclass->renderChat() ){
                $vodclass->burnChat( 300, true );
                $response->getBody()->write("Chat rendered and burned<br>");
            }

        }

        return $response;

    }

    public function convert( Request $request, Response $response, $args ) {
        
        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json' );
        $vodclass->convert();

        $response->getBody()->write("VOD converted");

        return $response;

    }

    public function save( Request $request, Response $response, $args ) {
        
        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json' );
        $vodclass->save();

        $response->getBody()->write("VOD saved");

        return $response;

    }

    public function delete( Request $request, Response $response, $args ) {
        
        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json' );
        $vodclass->delete();

        $response->getBody()->write("VOD deleted");

        return $response;

    }

    public function download( Request $request, Response $response, $args ) {
        
        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json' );
        $vodclass->downloadVod();

        $response->getBody()->write("VOD downloaded");

        return $response;

    }

    public function check_mute( Request $request, Response $response, $args ) {
        
        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json' );

        if(!$vodclass->twitch_vod_id){
            $response->getBody()->write("VOD does not have an ID");
            return $response;
        }
        
        $isMuted = $vodclass->checkMutedVod();

        $response->getBody()->write("VOD " . $vod . " is " . ( $isMuted ? "truly" : "not" ) . " muted!");

        
        $vodclass->twitch_vod_muted = $isMuted;
        $vodclass->saveJSON();

        return $response;

    }

    public function troubleshoot( Request $request, Response $response, $args ) {
        
        $vod = $args['vod'];
        // $vod = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_GET['vod']);
        $username = explode("_", $vod)[0];

        $vodclass = new TwitchVOD();
        $vodclass->load( TwitchHelper::vod_folder($username) . DIRECTORY_SEPARATOR . $vod . '.json' );
                
        $issue = $vodclass->troubleshoot( isset( $_GET['fix'] ) );
        if($issue){
            $response->getBody()->write( $issue['text'] );
        }else{
            $response->getBody()->write( "found nothing wrong" );
        }
        
        if( isset( $_GET['fix'] ) && $issue['fixable'] ){
            $response->getBody()->write( "<br>trying to fix!" );
        }

        return $response;

    }

}