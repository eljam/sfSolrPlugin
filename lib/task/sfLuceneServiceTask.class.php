<?php
/*
 * This file is part of the sfLucenePlugin package
 * (c) 2009 Thomas Rabaix <thomas.rabaix@soleoweb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once(dirname(__FILE__).'/sfLuceneBaseTask.class.php');

/**
* Start solr web server, use Jetty WebServer
*
* @author Thomas Rabaix <thomas.rabaix@soleoweb.com>
* @package sfLucenePlugin
* @subpackage Tasks
* @version SVN: $Id: sfLuceneInitializeTask.class.php 12678 2008-11-06 09:23:10Z rande $
*/

class sfLuceneServiceTask extends sfLuceneBaseTask
{
  protected function configure()
  {
    $this->addArguments(array(
      new sfCommandArgument('application', sfCommandArgument::REQUIRED, 'The application name'),
      new sfCommandArgument('action', sfCommandArgument::REQUIRED, 'The action name')
    ));

    exec('which java', $output, $results);

    $java = $results == 0 ? $output[0] : false;

    $this->addOptions(array(
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'prod'),
      new sfCommandOption('java', null, sfCommandOption::PARAMETER_REQUIRED, 'the java binary', $java),

    ));

    $this->aliases = array('lucene-service');
    $this->namespace = 'lucene';
    $this->name = 'service';
    $this->briefDescription = 'start or stop the Solr server (only *nix plateform)';

    $this->detailedDescription = <<<EOF
The [lucene:service|INFO] start or stop the Solr server

The command use the Jetty WebServer

    [./symfony lucene:service myapp start|INFO]   start the Solr server
    [./symfony lucene:service myapp stop|INFO]    stop the Solr server
    [./symfony lucene:service myapp restart|INFO] restart the Solr server

You can also retrieve the status by calling :

    [./symfony lucene:service myapp status|INFO] restart the Solr server

Note about Jetty :

  Jetty is an open-source project providing a HTTP server, HTTP client and
  javax.servlet container. These 100% java components are full-featured,
  standards based, small foot print, embeddable, asynchronous and enterprise
  scalable. Jetty is dual licensed under the Apache Licence 2.0 and/or the
  Eclipse Public License 1.0. Jetty is free for commercial use and distribution
  under the terms of either of those licenses.

  more information about Jetty : http://www.mortbay.org/jetty/

EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    $app = $arguments['application'];
    $env = $options['env'];

    if(!is_executable($options['java'] ))
    {

      throw new sfException('Please provide a valid java executable file');
    }
    
    $action = $arguments['action'];

    switch($action)
    {
      case 'start':
       $this->start($app, $env);
       break;

     case 'stop':
       $this->stop($app, $env);

       break;

     case 'restart':
       $this->stop($app, $env);
       $this->start($app, $env);
       break;

     case 'status':
       $this->status($app, $env);
       break;
    }
  }

  public function isRunning($app, $env)
  {
    
    return @file_exists($this->getPidFile($app, $env));
  }

  public function start($app, $env)
  {
    if($this->isRunning($app, $env))
    {

      throw new sfException('Server is running, cannot start (pid file : '.$this->getPidFile($app, $env).')');
    }

    // start the jetty built in server
    $command = sprintf('cd %s/plugins/sfLucenePlugin/lib/vendor/Solr/example; java -Dsolr.solr.home=%s/config/solr/ -Dsolr.data.dir=%s/data/solr_index -jar start.jar > %s/solr_server_%s_%s.log 2>&1 & echo $!',
      sfConfig::get('sf_root_dir'),
      sfConfig::get('sf_root_dir'),
      sfConfig::get('sf_root_dir'),
      sfConfig::get('sf_root_dir').'/log',
      $app,
      $env
    );

    $this->logSection('exec ', $command);

    exec($command ,$op);

    $this->getFilesystem()->sh(sprintf('cd %s',
      sfConfig::get('sf_root_dir')
    ));

    $pid = (int)$op[0];
    file_put_contents($this->getPidFile($app, $env), $pid);

    $this->logSection("solr", "Server started with pid : ".$pid);
  }

  public function stop($app, $env)
  {
    if(!$this->isRunning($app, $env))
    {

      throw new sfException('Server is not running');
    }

    $pid = file_get_contents($this->getPidFile($app, $env));
    
    $this->getFilesystem()->sh("kill -9 ".$pid);

    unlink($this->getPidFile($app, $env));
  }

  public function status($app, $env)
  {

   
    if(!$this->isRunning($app, $env))
    {
      
      $this->log('pid file not presents');
      return;
    }

    $pid = file_get_contents($this->getPidFile($app, $env));

    exec("ps ax | grep $pid 2>&1", $output);

    while( list(,$row) = each($output) ) {

      $row_array = explode(" ", $row);
      $check_pid = $row_array[0];

      if($pid == $check_pid) {
        $this->log('server running');
        return;
      }
    }

    $this->log('server is not running');
  }

  public function getPidFile($app, $env)
  {
    $file = sprintf('%s/solr_index/%s_%s.pid',
      sfConfig::get('sf_data_dir'),
      $app,
      $env
    );

    return $file;
  }
}