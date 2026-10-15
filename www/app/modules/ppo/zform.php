<?php

namespace App\Modules\PPO;

use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Binding\PropertyBinding as Prop;
use App\Helper as H;
use App\System;

class ZForm extends \App\Pages\Base
{
    private $_pos;
    public $_list;

    public function __construct() {
        parent::__construct();

        $this->add(new Form('filter'))->onSubmit($this, 'OnRefresh');

        $this->filter->add(new DropDownChoice('pos', \App\Entity\Pos::findArray('pos_name', ''), 0));

        $this->add(new Form('stat'));
        $this->stat->add(new TextInput('nal'));
        $this->stat->add(new TextInput('bnal'));
        $this->stat->add(new TextInput('credit'));
        $this->stat->add(new TextInput('prepaid'));
        $this->stat->add(new TextInput('retnal'));
        $this->stat->add(new TextInput('retbnal'));
        $this->stat->add(new TextInput('cnt'));
        $this->stat->add(new TextInput('retcnt'));
        $this->stat->add(new TextInput('sin'));
        $this->stat->add(new TextInput('sout'));
        $this->stat->add(new CheckBox('onlyshift'));
        $this->stat->add(new SubmitButton("zclose"))->onClick($this, 'OnClose');


        $this->stat->setVisible(false);
        $this->add(new  ClickLink("sync", $this, "onSync")) ;
        $this->add(new  ClickLink("zt", $this, "onZT")) ;
        $this->add(new  Label("ztres"));

        $this->add(new DataView('list', new ArrayDataSource(new Prop($this, '_list')), $this, 'OnRow'));


    }

    public function OnRefresh($sender) {
        $pos_id = $this->filter->pos->getValue();
        if ($pos_id == 0) {
            return;
        }
        $this->_pos = \App\Entity\Pos::load($pos_id);

        $this->stat->setVisible(true);
        $this->stat->clean();

        // $this->sync->setVisible(true);

        $data = \App\Modules\PPO\PPOHelper::getStat($pos_id );

        foreach($data as $ch=>$row)  {
            if($ch ==1) {
                $this->stat->nal->setText($row['amount0']);
                $this->stat->bnal->setText($row['amount1']);
                $this->stat->credit->setText($row['amount2']);
                $this->stat->prepaid->setText($row['amount3']);
                $this->stat->cnt->setText($row['cnt']);
            }
            
            if($ch ==2) {
                $this->stat->retnal->setText($row['amount0']);
                $this->stat->retbnal->setText($row['amount1']);
                $this->stat->retcnt->setText($row['cnt']);
            }
        
            if($ch ==4) {
                $this->stat->sin->setText($row['amount0']);
            }
         
            if($ch ==5) {
                $this->stat->sout->setText($row['amount0']);
            }
            
            
        }
        
  

        $this->_list =  \App\Modules\PPO\PPOHelper::getStatList($pos_id);
        $this->list->Reload() ;

    }

    public function onRow($row) {
        $item = $row->getDataItem();


        $amount0=0;
        $amount1=0;
        $amount0r=0;
        $amount1r=0;
        $amount2=$item->amount2;
        $amount3=$item->amount3;

        if($item->checktype == "3") {
            $amount0r=$item->amount0;
            $amount1r=$item->amount1;

        } else {
            $amount0=$item->amount0;
            $amount1=$item->amount1;

        }
  

        $row->add(new Label("docnumber", $item->document_number));
        $row->add(new Label("amount0", H::fa($amount0)));
        $row->add(new Label("amount1", H::fa($amount1)));
        $row->add(new Label("amount2", H::fa($amount2)));
        $row->add(new Label("amount3", H::fa($amount3)));
        $row->add(new Label("amount0r", H::fa($amount0r)));
        $row->add(new Label("amount1r", H::fa($amount1r)));

        //    $row->add(new ClickLink("fisc", $this,"onFisc" ))->setVisible( strlen($item->fiscnumber) == 0 );
        $row->add(new ClickLink("del", $this, "onDel"))->setVisible(strlen($item->fiscnumber) == 0);


    }

    public function onFisc($sender) {
        $item = $sender->getOwner()->getDataItem();
        $doc=\App\Entity\Doc\Document::getFirst("document_number=" . \App\Entity\Doc\Document::qstr($item->document_number)) ;
        if($doc==null) {

            return ;
        }
        $doc->headerdata["fiscalnumberpos"]  = $this->_pos->fiscalnumber;


        $ret = \App\Modules\PPO\PPOHelper::check($doc);


        if ($ret['success'] == false && ($ret['doclocnumber'] ?? 0) > 0) {
            //повторяем для  нового номера
            $this->_pos->fiscdocnumber = $ret['doclocnumber'];
            $this->_pos->save();
            $ret = \App\Modules\PPO\PPOHelper::check($doc);
        }
        if ($ret['success'] == false) {
            $this->setErrorTopPage($ret['data']);

            return;
        } else {

            if ( ($ret['docnumber'] ?? 0) > 0) {
                $this->_pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                $this->_pos->save();
                $doc->headerdata["fiscalnumber"] = $ret['docnumber'];
            } else {
                $this->setError(__('common.ne_poverneno_fiskalnyi_nomer'));

                return;
            }
        }
        $doc->save();


        $this->OnRefresh($this->filter) ;

    }

    public function onDel($sender) {
        $item = $sender->getOwner()->getDataItem();
        \App\Modules\PPO\PPOHelper::delStat($item->zf_id);

        $this->OnRefresh($this->filter) ;

    }

    public function OnClose($sender) {
        if ($this->_pos->pos_id == 0) {
            $this->setError(__('mod.ppo.zform.ne_vybranyi_terminal'));
            return;
        }

        $ret=true;
        if($this->stat->onlyshift->isChecked()==false) {
            $ret = $this->zform();
        }

        if ($ret == true) {
            //$this->closeshift();
            $pos = \App\Entity\Pos::load($this->_pos->pos_id);

            $ret = \App\Modules\PPO\PPOHelper::shift($this->_pos->pos_id, false);

            if ($ret['success'] == false && ($ret['doclocnumber']??0) > 0) {
                //повторяем для  нового номера
                $this->_pos->fiscdocnumber = $ret['doclocnumber'];
                $this->_pos->save();
                $ret = \App\Modules\PPO\PPOHelper::shift($this->_pos->pos_id, false);

            }

            if ($ret['success'] != true) {
                $this->setErrorTopPage($ret['data']);
            } else {
                \App\Modules\PPO\PPOHelper::clearStat($this->_pos->pos_id);
                $this->setSuccess(__('common.zmina_zakryta'));
                $this->stat->clean();
                $this->OnRefresh($this->filter) ;
                $this->_pos->fiscdocnumber++;
                $this->_pos->save();

            }

        }
    }

    public function zform() {

        $stat = array();
        $row = array();
        
        $row['amount0'] = $this->stat->nal->getDouble();
        $row['amount1'] = $this->stat->bnal->getDouble();
        $row['amount2'] = $this->stat->credit->getDouble();
        $row['amount3'] = $this->stat->prepaid->getDouble();
        $row['cnt'] = $this->stat->cnt->getInt();

        $stat[1]= $row;
        
        $row = array();
        $row['amount0'] = $this->stat->retnal->getDouble();
        $row['amount1'] = $this->stat->retbnal->getDouble();
 
        $row['cnt'] = $this->stat->retcnt->getInt();
        $stat[2]= $row;
     
        $stat[4]= ['amount0'=>$this->stat->sin->getDouble()];
        $stat[5]= ['amount0'=>$this->stat->sout->getDouble()];
     
        $ret = \App\Modules\PPO\PPOHelper::zform($this->_pos->pos_id, $stat );
        if (strpos($ret['data'], 'ZRepAlreadyRegistered')) {
            return true;
        }
        if ($ret['success'] == false && ( $ret['docnumber'] ??0) > 0) {
            //повторяем для  нового номера
            $this->_pos->fiscdocnumber = $ret['docnumber'];
            $this->_pos->save();
            $ret = \App\Modules\PPO\PPOHelper::zform($this->_pos->pos_id, $stat, $rstat);
        }
        if ($ret['success'] == false) {
            $this->setErrorTopPage($ret['data']);
            return false;
        } else {

            if (($ret['docnumber'] ?? 0) > 0) {
                $this->_pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                $this->_pos->save();
                return true;
            } else {
                $this->setError(__('common.ne_poverneno_fiskalnyi_nomer'));
                return false;
            }
        }



    }

    public function onSync($sender) {

        $pos_id = $this->filter->pos->getValue();
        if ($pos_id == 0) {
            $this->setError(__('mod.ppo.zform.ne_vybranyi_terminal'));
            return;
        }

        \App\Modules\PPO\PPOHelper::sync($pos_id)  ;

        $this->OnRefresh($this->filter)   ;

    }

    // данные  с налоговой
    public function onZT($sender) {
        $this->ztres->setText("");
        $pos_id = $this->filter->pos->getValue();
        if ($pos_id == 0) {
            $this->setError(__('mod.ppo.zform.ne_vybranyi_terminal'));

            return;
        }

        $pos = \App\Entity\Pos::load($this->_pos->pos_id);
     
        $ret = PPOHelper::shiftTotal(  $pos) ;
        if($ret == false) {
            $this->setError(__('mod.ppo.zform.server_nedostupnyi_abo_zmina_zakryta'));
            return ;
        }
        if(!is_array($ret['Totals'])) {
            $this->setError(__('mod.ppo.zform.server_nedostupnyi_abo_zmina_zakryta')) ;
            return ;
        }
        $zt="";
        if(is_array($ret['Totals']['Real']['PayForm'] ??null)) {
            $zt .=__('mod.ppo.zform.realizatsiia');
            foreach($ret['Totals']['Real']['PayForm'] as $form) {
                $zt .= $form['PayFormName']." ".$form['Sum']."<br>" ;

            }
            $zt .= __('mod.ppo.zform.chekiv').$ret['Totals']['Real']['OrdersCount'] ;
        }
        if(is_array($ret['Totals']['Ret']['PayForm']??null)) {
            $zt .=__('mod.ppo.zform.povernennia');
            foreach($ret['Totals']['Ret']['PayForm'] as $form) {
                $zt .= $form['PayFormName']." ".$form['Sum']."<br>" ;

            }
            $zt .= __('mod.ppo.zform.chekiv').$ret['Totals']['Ret']['OrdersCount'] ;
        }
        if( ($ret['Totals']['ServiceInput'] ??0) >0) {
             $zt .= __('mod.ppo.zform.sluzhbove_vnesennia').$ret['Totals']['ServiceInput'] ;
        }
        if( ($ret['Totals']['ServiceOutput'] ??0) >0) {
             $zt .= __('mod.ppo.zform.sluzhbova_vydacha').$ret['Totals']['ServiceOutput'] ;
        }
        $this->ztres->setText($zt, true);

    }




}
