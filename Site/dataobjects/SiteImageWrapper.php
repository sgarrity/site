<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once 'Site/dataobjects/SiteImage.php';
require_once 'Site/dataobjects/SiteImageDimensionBindingWrapper.php';

/**
 * A recordset wrapper class for SiteImage objects
 *
 * To load images more efficiently, see {@link SiteImageLazyWrapper}.
 *
 * @package   Site
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       SiteImage
 * @see       SiteImageLazyWrapper
 */
class SiteImageWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected properties

	protected $binding_table = 'ImageDimensionBinding';
	protected $binding_table_image_field = 'image';

	// }}}

	// {{{ public function __construct()

	/**
	 * Creates a new recordset wrapper
	 *
	 * @param MDB2_Result $recordset optional. The MDB2 recordset to wrap.
	 */
	public function __construct($recordset = null)
	{
		parent::__construct($recordset);

		$this->attachDimensionBindings();
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->row_wrapper_class = SwatDBClassMap::get('SiteImage');
		$this->index_field = 'id';
	}

	// }}}
	// {{{ protected function attachDimensionBindings()

	/**
	 * Attaches dimension bindings to the recordset
	 */
	protected function attachDimensionBindings(array $dimensions = null)
	{
		if ($this->getCount() > 0 && count($dimensions) > 0) {
			$image_ids = array();
			foreach ($this->getArray() as $image)
				$image_ids[] = $this->db->quote($image->id, 'integer');

			$sql = $this->getDimensionQuery($image_ids, $dimensions);
			$wrapper_class = $this->getImageDimensionBindingWrapperClassName();
			$bindings = SwatDB::query($this->db, $sql, $wrapper_class);

			if (count($bindings) == 0)
				return;

			$last_image = null;
			foreach ($bindings as $binding) {
				$field = $this->binding_table_image_field;

				if ($last_image === null ||
					$last_image->id !== $binding->$field) {

					if ($last_image !== null) {
						$wrapper->reindex();
						$last_image->dimension_bindings = $wrapper;
					}

					$last_image = $this->getByIndex($binding->$field);
					$wrapper = new $wrapper_class();
				}

				$wrapper->add($binding);
			}

			$wrapper->reindex();
			$last_image->dimension_bindings = $wrapper;
		}
	}

	// }}}
	// {{{ protected function getImageDimensionBindingWrapperClassName()

	protected function getImageDimensionBindingWrapperClassName()
	{
		return SwatDBClassMap::get('SiteImageDimensionBindingWrapper');
	}

	// }}}
	// {{{ protected function getDimensionQuery()

	protected function getDimensionQuery($image_ids, array $dimensions = null)
	{
		if ($dimensions === null) {
			$dimension_sql = '';
		} else {
			$dimension_shortnames = $dimensions;
			foreach ($dimension_shortnames as &$shortname)
				$shortname = $this->db->quote($shortname, 'text');

			$dimension_sql = sprintf('and %s.dimension in (
				select id from ImageDimension where shortname in (%s))',
				$this->binding_table,
				implode(', ', $dimension_shortnames));
		}

		$sql = sprintf('select %1$s.*
			from %1$s
			where %1$s.%2$s in (%3$s) %4$s
			order by %2$s',
			$this->binding_table,
			$this->binding_table_image_field,
			implode(',', $image_ids),
			$dimension_sql);

		return $sql;
	}

	// }}}
}

?>
