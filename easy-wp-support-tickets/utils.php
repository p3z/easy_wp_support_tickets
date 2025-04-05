<?php

class EwstUtils{
    
    public static function init_session(){
    
        if ( !session_id() ){
            session_start();
            
        } 
        
    }// end fn

}// end class

?>