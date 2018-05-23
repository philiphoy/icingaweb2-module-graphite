<?php

use Icinga\Web\Controller\ActionController;
use Icinga\Web\Url;

class Graphite_IndexController extends ActionController
{
    protected $grapher;

    public function init()
    {
        $this->grapher = new \Icinga\Module\Graphite\Grapher;
    }

    public function indexAction()
    {
        $this->view->iframe_w = $this->getParam('graphite_iframe_w');
        $this->view->iframe_h = $this->getParam('graphite_iframe_h');

        if ($this->grapher->getRemoteFetch()) {
            $this->view->url = Url::fromPath('graphite/index/graph', array(
                                   'target' => $this->getParam('graphite_url')
                               ));
        } else {
            $this->view->url = urldecode($this->getParam('graphite_url'));
        }
    }

    public function graphAction()
    {
        $this->_helper->layout()->disableLayout();

        $target = $this->getParam('target');
        $from = $this->getParam('from');

        $largeImgUrl = $this->grapher->getLargeImgUrl($target, $from);

        if ($this->grapher->getRemoteFetch()) {
            $largeImgUrl = $this->grapher->inlineImage($largeImgUrl);
        }

        $this->view->largeImgUrl = $largeImgUrl;
        $this->view->target = $target;
    }
}
