<?php

namespace Macro\Error;

class MissingMacroException extends MacroException
{

    protected $_messageTemplate = 'Macro class %s could not be found.';

}
