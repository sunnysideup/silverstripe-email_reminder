<?php


interface EmailReminder_ReplacerClassInterface
{

    /**
     * replaces all instances of certain
     * strings in the string and returns the string
     * @param string $str
     * 
     * @return string
     */ 
    public function replace($str);

    /**
     * provides and array of replacements like this:
     *
     *     [string to replace] => 'description of what it does'
     * 
     * @return array
     */
    public function replaceHelpList();

}
