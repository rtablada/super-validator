<?php namespace Rtablada\SuperValidator;

use Illuminate\Validation\Factory as LaravelValidator;
use Illuminate\Database\Eloquent\Model as Eloquent;

abstract class BaseValidator
{
	/**
	 * Laravel Validator Instance.
	 *
	 * @var Illuminate\Validation\Factory
	 */
	protected $validator;

	/**
	 * Data used for validations.
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Array of errors set if validation fails.
	 *
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Basic rules use for validation.
	 *
	 * @var array
	 */
	protected $rules = array();

	/**
	 * Custom messages hash used during validation.
	 *
	 * @var array
	 */
	protected $messages = array();

	/**
	 * Table name used for unique rules
	 *
	 * @var string
	 */
	protected $tableName;

	/**
	 * List of unique properties when building
	 * rules use for validateForExisting.
	 *
	 * @var array
	 */
	protected $uniques = array();

	/**
	 * Constructor for validation instance.
	 *
	 * @param Illuminate\Validation\Factory $validator
	 */
	public function __construct(LaravelValidator $validator)
	{
		$this->validator = $validator;
	}

	/**
	 * Sets data for validations on the class.
	 *
	 * @param  array  $data
	 * @return void
	 */
	public function with(array $data)
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * Validates and sets errors if fails.
	 *
	 * @param  array $data
	 * @param  array $rules
	 * @return boolean
	 */
	public function validate(array $data, array $rules)
	{
		$validator = $this->validator->make(
			$data,
			$rules,
			$this->messages
		);

		if ($validator->fails()) {
			$this->errors = $validator->messages();

			return false;
		}

		return true;
	}

	/**
	 * Validate data with dynamic existing model
	 *
	 * @param  array  $data
	 * @param  array  $rules
	 * @param  Illuminate\Database\Eloquent $model
	 * @param  array $uniques
	 * @return boolean
	 */
	public function validateForExisting(array $data, array $rules, Eloquent $model, $uniques = null)
	{
		$uniques = $uniques ?: $this->uniques;
		$rules = $this->buildRulesForUniquesWithExisting($rules, $uniques, $model);

		return $this->validate($data, $rules);
	}

	/**
	 * Check if the current data
	 * and rules pass validation.
	 *
	 * @return boolean
	 */
	public function passes()
	{
		return $this->validate(
			$this->data,
			$this->rules
		);
	}

	/**
	 * Get errors of failing vaidation
	 *
	 * @return array
	 */
	public function errors()
	{
		return $this->errors;
	}

	/**
	 * Gets rules for specified method for validation.
	 *
	 * @param  string $method
	 * @return array
	 */
	protected function getRules($method)
	{
		$rulesName = camel_case($method) . 'Rules';

		return isset($this->{$rulesName}) ? $this->{$rulesName} : $this->rules;
	}

	/**
	 * Rebuilds validation rules with unique rules included
	 *
	 * @param  array  $rules
	 * @param  array  $uniques
	 * @param  Illuminate\Database\Eloquent $model
	 * @return array
	 */
	protected function buildRulesForUniques(array $rules, array $uniques, Eloquent $model = null)
	{
		$modelId = $model ? $model->{$model->getKeyName()} : null;

		foreach ($uniques as $key) {
			$rule = $this->buildUniqueRule($key, $modelId);
			if (isset($rules[$key])) {
				$rules[$key] .= '|' . $rule;
			} else {
				$rules[$key] = $rule;
			}
		}

		return $rules;
	}

	/**
	 * Gets a singular unique rule with a modelId constraint
	 *
	 * @param  string $key
	 * @param  integer $modelId
	 * @return string
	 */
	protected function buildUniqueRule($key, $modelId)
	{
		if ($modelId) {
			return "unique:{$this->tableName},{$key},{$modelId}";
		} else {
			return "unique:{$this->tableName},{$key}";
		}
	}

	/**
	 * Match validations for other properties
	 *
	 * @param  string $method
	 * @param  array $parameters
	 * @return boolean
	 */
	public function __call($method, $parameters)
	{
		$matches = array();

		if (preg_match('/validate(.+)(ForExisting)/', $method, $matches)) {
			$rules = $this->getRules($matches[1]);
			if (isset($matches[2]) && $parameters[1]) {
				$uniques = $this->getUniques($matches[1]);

				return $this->validateForExisting($parameters[0], $rules, $matches[1], $uniques);
			}

			return $this->validate($parameters[0], $rules);
		}
	}
}
