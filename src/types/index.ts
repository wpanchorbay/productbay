/**
 * ProductBay Type Definitions
 *
 * Central type definitions for the ProductBay table configuration system.
 * These interfaces match the backend schema defined in DataArchitectureSpecification.md
 * and are used throughout the React admin interface.
 */

/**
 * Product Interface
 * Matches WooCommerce product data subset used in the frontend.
 */
export interface Product {
	id: number;
	name: string;
	sku: string;
	price: string;
	image: string;
}

/**
 * Category Interface
 * Matches WooCommerce product category data used in the frontend.
 */
export interface Category {
	id: number;
	name: string;
	count: number;
	slug: string;
}

/* =============================================================================
 * Column Visibility Modes
 * =============================================================================
 * Maps to CSS media query classes in the frontend renderer.
 * Controls when columns are visible based on device screen size.
 * ============================================================================= */
export type VisibilityMode =
	| 'default' // Use system default visibility
	| 'all' // Visible on all devices
	| 'none' // Hidden on all devices
	| 'mobile' // Visible only on mobile (block md:hidden)
	| 'tablet' // Visible only on tablet
	| 'desktop' // Visible only on desktop (hidden lg:block)
	| 'not-mobile' // Hidden on mobile (hidden md:block)
	| 'not-tablet' // Hidden on tablet
	| 'not-desktop' // Hidden on desktop (block lg:hidden)
	| 'min-tablet'; // Visible on tablet and larger (hidden md:block)

/* =============================================================================
 * Column Types
 * =============================================================================
 * Available column types that can be added to a product table.
 * Each type has specific rendering logic and settings.
 * ============================================================================= */
export type ColumnType =
	| 'image' // Product image with customizable size and link behavior
	| 'name' // Product name/title
	| 'price' // Product price (regular and sale)
	| 'sku' // Stock Keeping Unit
	| 'stock' // Stock status/quantity
	| 'button' // Add to cart button
	| 'date' // Product date (published/modified)
	| 'summary' // Short description
	| 'tax' // Taxonomy (category, tag, attribute)
	| 'cf' // Custom field (meta)
	| 'combined' // Multiple elements combined in one cell
	| 'rating'; // Product average star rating

export interface CombinedElement {
	/** Unique ID for drag and drop */
	id: string;
	/** The type of attribute to display */
	type: ColumnType;
	/** Type-specific settings for this element (e.g. prefix, suffix, imageSize) */
	settings?: Record<string, unknown>;
}

/* =============================================================================
 * Column Interface
 * =============================================================================
 * Represents a single column in the product table.
 * Stored in _pb_columns meta as an ordered array.
 * ============================================================================= */
export interface Column {
	/** Unique identifier for React keys and DnD */
	id: string;

	/** Column type determines rendering logic */
	type: ColumnType;

	/** Display heading for the column header */
	heading: string;

	/** Advanced display settings */
	advanced: {
		/** Whether to show the column heading */
		showHeading: boolean;

		/**
		 * Column width configuration
		 * - 'auto': Let browser determine width (recommended default)
		 * - 'px': Fixed pixel width
		 * - '%': Percentage of table width
		 */
		width: {
			value: number;
			unit: 'auto' | 'px' | '%';
		};

		/** Responsive visibility mode */
		visibility: VisibilityMode;

		/** Display order (used for sorting) */
		order: number;
	};

	/**
	 * Type-specific settings
	 * - image: { imageSize, linkTarget }
	 * - tax: { taxonomy, linkToArchive }
	 * - cf: { metaKey }
	 * - combined: { layout, elements }
	 */
	settings?: Record<string, unknown>;
}

/* =============================================================================
 * Data Source Interface
 * =============================================================================
 * Defines which products to display in the table.
 * Stored in _pb_source meta.
 * ============================================================================= */
export type SourceType = 'all' | 'sale' | 'category' | 'specific';

export interface DataSource {
	/** Source type determines query strategy */
	type: SourceType;

	/** Query arguments for filtering products */
	queryArgs: {
		/** Category IDs for 'category' source type */
		categoryIds: number[];

		/** Tag IDs for additional filtering */
		tagIds: number[];

		/** Product IDs for 'specific' source type */
		postIds: number[];

		/** Full product objects for 'specific' source type (to avoid re-fetching on load) */
		productObjects?: Record<number, Product>;

		/** Product IDs to exclude from results */
		excludes: number[];

		/** Stock status filter */
		stockStatus: 'any' | 'instock' | 'outofstock';

		/** Price range filter */
		priceRange: {
			min: number;
			max: number | null;
		};
	};

	/** Sorting configuration */
	sort: {
		/** Field to sort by (price, date, title, etc.) */
		orderBy: string;

		/** Sort direction */
		order: 'ASC' | 'DESC';
	};
}

/* =============================================================================
 * Table Settings Interface
 * =============================================================================
 * Functional behavior toggles and configurations.
 * Stored in _pb_settings meta.
 * ============================================================================= */
export interface TableSettings {
	/** Feature toggles */
	features: {
		/** Enable search box */
		search: boolean;

		/** Enable column sorting */
		sorting: boolean;

		/** Enable pagination */
		pagination: boolean;

		/** Enable CSV/PDF export */
		export: boolean;

		/** Bulk Selection Settings */
		bulkSelect: {
			enabled: boolean;
			position: 'first' | 'last';
			width: {
				value: number;
				unit: 'px' | '%' | 'auto';
			};
			visibility: VisibilityMode;
		};

		/** Show variation badges after adding variable products */
		variationBadges: boolean;

		/** Show clear all button when items are selected */
		clearAllButton: boolean;

		/** Selected items popup configuration */
		selectedItemsPanel: {
			enabled: boolean;
		};

		/** Enable native image lightbox */
		lightbox: boolean;

		/** Enable Support for Variable & Grouped Products */
		variableGrouped?: boolean;

		/** Pro: Price filter configuration */
		priceFilter?: {
			enabled: boolean;
			mode: 'slider' | 'input' | 'both';
			step: number;
			customMin?: number | null;
			customMax?: number | null;
		};

		/** @deprecated Use variableProductMode / groupedProductMode instead. Kept for backward compatibility. */
		variationsMode?: 'inline' | 'popup' | 'nested' | 'separate';

		/** Pro: Display mode for variable products (products with attribute-based variations) */
		variableProductMode?: 'inline' | 'popup' | 'nested' | 'separate';

		/** Display mode for grouped products (products containing child simple products). 'inline' is Free, others are Pro. */
		groupedProductMode?: 'inline' | 'popup' | 'nested' | 'separate';

		/** Pro: Whether nested rows should be expanded by default */
		nestedDefaultExpanded?: boolean;

		/** Pro: Show child/variation count subtitle below product name */
		showChildCount?: boolean;
	};

	/** Pagination configuration */
	pagination: {
		/** Products per page */
		limit: number;

		/** Pagination mode (Pro only: load_more, infinite) */
		mode?: 'standard' | 'load_more' | 'infinite';

		/** Pagination position (top, bottom, both) */
		position: 'top' | 'bottom' | 'both';
	};

	/** Cart behavior settings */
	cart: {
		/** Enable add to cart functionality */
		enable: boolean;

		/** Cart interaction method */
		method: 'button' | 'checkbox' | 'text';

		/** Show quantity selector */
		showQuantity: boolean;

		/** Use AJAX for add to cart */
		ajaxAdd: boolean;

		/** Custom Add to Cart text for this table. If empty, falls back to global setting. */
		addToCartText?: string;

		/** Custom Select Options (opener) text for this table. If empty, falls back to global setting. */
		selectOptionsText?: string;
	};

	/** Filter configuration */
	filters: {
		/** Enable filters */
		enabled: boolean;

		/** Show category filter dropdown */
		showCategory: boolean;

		/** Show product type filter dropdown */
		showType: boolean;

		/** Active taxonomy filters (product_cat, pa_color, etc.) */
		activeTaxonomies: string[];

		/** Show price range slider (Pro) */
		showPriceRange?: boolean;
	};
}

/* =============================================================================
 * Table Style Interface
 * =============================================================================
 * Visual design tokens for table appearance.
 * Stored in _pb_style meta.
 * ============================================================================= */
export interface TableStyle {
	/** Header row styling */
	header: {
		bgColor: string;
		textColor: string;
		fontSize: string;
	};

	/** Body/row styling */
	body: {
		bgColor: string;
		textColor: string;
		rowAlternate: boolean;
		altBgColor: string;
		altTextColor: string;
		borderColor: string;
	};

	/** Button styling (add to cart, etc.) */
	button: {
		bgColor: string;
		textColor: string;
		borderRadius: string;
		icon: string;
		/** Hover state colors */
		hoverBgColor: string;
		hoverTextColor: string;
	};

	/** Layout & Spacing configuration */
	layout: {
		/** Table border style */
		borderStyle: 'none' | 'solid' | 'dashed';
		/** Table border color */
		borderColor: string;
		/** Enable/Disable border radius entirely */
		borderRadiusEnabled: boolean;
		/** Table border radius */
		borderRadius: string;
		/** Cell padding density */
		cellPadding: 'compact' | 'normal' | 'spacious';
	};

	/** Typography settings */
	typography: {
		/** Header row font weight */
		headerFontWeight: 'normal' | 'bold' | 'extrabold';
		/** Header row text transform */
		headerTextTransform: 'capitalize' | 'uppercase' | 'lowercase' | 'normal-case';
	};

	/** Hover & Interaction effects */
	hover: {
		/** Enable row hover effect */
		rowHoverEnabled: boolean;
		/** Row hover background color */
		rowHoverBgColor: string;
		/** Row hover text color */
		rowHoverTextColor: string;
	};

	/** Responsive display settings */
	responsive: {
		/** Responsive display mode */
		mode: 'standard' | 'stack' | 'accordion';
	};
}

/* =============================================================================
 * Product Table Interface
 * =============================================================================
 * Represents the complete configuration of a product table.
 * Matches backend structure.
 * ============================================================================= */
export interface ProductTable {
	id?: number;
	title: string;
	shortcode?: string;
	status: 'publish' | 'private';
	date?: string;
	modifiedDate?: string;
	productCount?: number;
	permalink?: string;
	source: DataSource;
	columns: Column[];
	settings: TableSettings;
	style: TableStyle;
}

/** Generates a unique column ID */
export const generateColumnId = (): string =>
	`col_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;

/** Creates a default TableSettings configuration */
export const createDefaultSettings = (): TableSettings => ({
	features: {
		search: true,
		sorting: true,
		pagination: true,
		export: false,
		bulkSelect: {
			enabled: true,
			position: 'last',
			width: { value: 64, unit: 'px' },
			visibility: 'all',
		},
		variationBadges: true,
		clearAllButton: true,
		selectedItemsPanel: {
			enabled: true,
		},
		lightbox: true,
		variableGrouped: false,
		priceFilter: {
			enabled: false,
			mode: 'both',
			step: 1,
			customMin: null,
			customMax: null,
		},
		variationsMode: 'inline',
		variableProductMode: 'inline',
		groupedProductMode: 'inline',
		nestedDefaultExpanded: false,
		showChildCount: true,
	},
	pagination: {
		limit: 10,
		mode: 'standard',
		position: 'bottom',
	},
	cart: {
		enable: true,
		method: 'button',
		showQuantity: true,
		ajaxAdd: true,
	},
	filters: {
		enabled: true,
		showCategory: true,
		showType: true,
		activeTaxonomies: ['product_cat'],
		showPriceRange: false,
	},
});

/** Creates default source configuration */
export const createDefaultSource = (): DataSource => ({
	type: 'all',
	queryArgs: {
		categoryIds: [],
		tagIds: [],
		postIds: [],
		excludes: [],
		stockStatus: 'any',
		priceRange: { min: 0, max: null },
	},
	sort: {
		orderBy: 'date',
		order: 'DESC',
	},
});

/** Creates default style configuration */
export const createDefaultStyle = (): TableStyle => ({
	header: {
		bgColor: '#f8f9fa',
		textColor: '#333333',
		fontSize: '14px',
	},
	body: {
		bgColor: '#ffffff',
		textColor: '#555555',
		rowAlternate: false,
		altBgColor: '#f9f9f9',
		altTextColor: '#555555',
		borderColor: '#eeeeee',
	},
	button: {
		bgColor: '#2271b1',
		textColor: '#ffffff',
		borderRadius: '4px',
		icon: 'cart',
		hoverBgColor: '#135e96',
		hoverTextColor: '#ffffff',
	},
	layout: {
		borderStyle: 'solid',
		borderColor: '#e5e7eb',
		borderRadiusEnabled: true,
		borderRadius: '8px',
		cellPadding: 'normal',
	},
	typography: {
		headerFontWeight: 'bold',
		headerTextTransform: 'uppercase',
	},
	hover: {
		rowHoverEnabled: true,
		rowHoverBgColor: '#f3f4f6',
		rowHoverTextColor: '',
	},
	responsive: {
		mode: 'standard',
	},
});

/** Creates default columns for a new table */
export const createDefaultColumns = (): Column[] => [
	{
		id: 'col_image_default',
		type: 'image',
		heading: 'Image',
		advanced: {
			showHeading: true,
			width: { value: 92, unit: 'px' },
			visibility: 'all',
			order: 1,
		},
		settings: {
			imageSize: 'thumbnail',
			linkTarget: 'product',
		},
	},
	{
		id: 'col_name_default',
		type: 'name',
		heading: 'Product',
		advanced: {
			showHeading: true,
			width: { value: 0, unit: 'auto' },
			visibility: 'all',
			order: 2,
		},
	},
	{
		id: 'col_price_default',
		type: 'price',
		heading: 'Price',
		advanced: {
			showHeading: true,
			width: { value: 0, unit: 'auto' },
			visibility: 'all',
			order: 3,
		},
	},
	{
		id: 'col_button_default',
		type: 'button',
		heading: 'Buy',
		advanced: {
			showHeading: true,
			width: { value: 0, unit: 'auto' },
			visibility: 'all',
			order: 4,
		},
	},
];
