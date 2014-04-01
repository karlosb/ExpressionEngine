
<?php
namespace EllisLab\ExpressionEngine\Core\Validation\Rule;

use EllisLab\ExpressionEngine\Core\Validation\ValidationRule as ValidationRule;

/**
 *
 */
class Integer extends ValidationRule {

	public function validate($value)
	{
		return (bool)preg_match( '/^[\-+]?[0-9]+$/', $value);
	}

}