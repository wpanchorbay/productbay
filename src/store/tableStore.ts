/**
 * Table Store
 *
 * The central engine for table configuration in ProductBay.
 * Manages the state for Source, Columns, Settings, and Style.
 * Includes persistence logic (load/save) and category caching.
 *
 * @since 1.0.0
 */
import { create } from 'zustand';
import { apiFetch } from '@/utils/api';
import type {
	Column,
	DataSource,
	TableSettings,
	TableStyle,
	ProductTable,
	SourceType,
	Product,
	Category,
	TableStatus,
	EditableTableStatus,
} from '@/types';
import {
	createDefaultSource as defaultSource,
	createDefaultSettings as defaultSettings,
	createDefaultStyle as defaultStyle,
	createDefaultColumns as defaultColumns,
} from '@/types';

/* =============================================================================
 * Existing Types (Preserved for backward compatibility)
 * ============================================================================= */

export interface SourceStats {
	categories: number;
	products: number;
}

/* =============================================================================
 * Table Store State Interface
 * =============================================================================
 * Central state management for table configuration.
 * Uses 4-key structure matching backend: source, columns, settings, style
 * ============================================================================= */

interface TableStore {
	// =========================================================================
	// Core Table State (4-key structure)
	// =========================================================================

	/** Table metadata */
	tableId: number | null;
	tableTitle: string;
	tableStatus: TableStatus;
	permalink?: string;

	/** Data source configuration (_pb_source) */
	source: DataSource;

	/** Column configuration (_pb_columns) */
	columns: Column[];

	/** Functional settings (_pb_settings) */
	settings: TableSettings;

	/** Visual styling (_pb_style) */
	style: TableStyle;

	// =========================================================================
	// UI State
	// =========================================================================

	isLoading: boolean;
	isSaving: boolean;
	error: string | null;
	isDirty: boolean; // Track unsaved changes

	// =========================================================================
	// Category Cache State (preserved from original)
	// =========================================================================

	categories: Category[];
	categoriesLoading: boolean;
	categoriesLastFetched: number | null;

	// =========================================================================
	// Source Statistics State (preserved from original)
	// =========================================================================

	sourceStats: Record<string, SourceStats>;
	sourceStatsLoading: Record<string, boolean>;

	// =========================================================================
	// Legacy State (for backward compatibility during migration)
	// =========================================================================

	/** @deprecated Use source.queryArgs.categoryIds instead */
	tableData: {
		title: string;
		source_type: string;
		columns: { id: string; label: string }[];
		config: {
			categories?: number[];
			products?: number[];
			productObjects?: Record<number, Product>;
			[key: string]: unknown;
		};
	};

	// =========================================================================
	// Core Actions
	// =========================================================================

	/** Set table title */
	setTitle: (title: string) => void;

	/** Set table status (publish/private) */
	setStatus: (status: EditableTableStatus) => void;

	// =========================================================================
	// Source Actions
	// =========================================================================

	/** Set source type (all, sale, category, specific) */
	setSourceType: (type: SourceType) => void;

	/** Update source query arguments */
	setSourceQueryArgs: (args: Partial<DataSource['queryArgs']>) => void;

	/** Update source sort configuration */
	setSourceSort: (sort: Partial<DataSource['sort']>) => void;

	// =========================================================================
	// Column Actions
	// =========================================================================

	/** Add a new column */
	addColumn: (column: Column) => void;

	/** Update an existing column */
	updateColumn: (columnId: string, updates: Partial<Column>) => void;

	/** Remove a column */
	removeColumn: (columnId: string) => void;

	/** Reorder columns (for drag-and-drop) */
	reorderColumns: (sourceIndex: number, destinationIndex: number) => void;

	// =========================================================================
	// Settings Actions
	// =========================================================================

	/** Update feature toggles */
	setFeatures: (features: Partial<TableSettings['features']>) => void;

	/** Update pagination settings */
	setPagination: (pagination: Partial<TableSettings['pagination']>) => void;

	/** Update cart settings */
	setCart: (cart: Partial<TableSettings['cart']>) => void;

	/** Update filter settings */
	setFilters: (filters: Partial<TableSettings['filters']>) => void;

	// =========================================================================
	// Style Actions
	// =========================================================================

	/** Update header styles */
	setHeaderStyle: (header: Partial<TableStyle['header']>) => void;

	/** Update body styles */
	setBodyStyle: (body: Partial<TableStyle['body']>) => void;

	/** Update button styles */
	setButtonStyle: (button: Partial<TableStyle['button']>) => void;

	/** Update layout styles */
	setLayoutStyle: (layout: Partial<TableStyle['layout']>) => void;

	/** Update typography styles */
	setTypographyStyle: (typography: Partial<TableStyle['typography']>) => void;

	/** Update hover styles */
	setHoverStyle: (hover: Partial<TableStyle['hover']>) => void;

	/** Update responsive styles */
	setResponsiveStyle: (responsive: Partial<TableStyle['responsive']>) => void;

	// =========================================================================
	// Persistence Actions
	// =========================================================================

	/** Reset store to defaults */
	resetStore: () => void;

	/** Load table from API by ID */
	loadTable: (id: number) => Promise<void>;

	/** Update store state from a table data object */
	applyTableData: (data: ProductTable) => void;

	/** Save table to API */
	saveTable: () => Promise<boolean>;

	// =========================================================================
	// Category Cache Actions (preserved from original)
	// =========================================================================

	preloadCategories: () => Promise<void>;
	refreshCategoriesIfStale: () => Promise<void>;
	forceReloadCategories: () => Promise<void>;

	// =========================================================================
	// Source Statistics Actions (preserved from original)
	// =========================================================================

	fetchSourceStats: (sourceType: string) => Promise<void>;

	// =========================================================================
	// Legacy Actions (for backward compatibility)
	// =========================================================================

	/** @deprecated Use specific setters instead */
	setTableData: (data: Partial<TableStore['tableData']>) => void;

	/** @deprecated - not used in new architecture */
	currentStep: number;
	setStep: (step: number) => void;
}

/* =============================================================================
 * Constants
 * ============================================================================= */

const CACHE_KEY = 'productbay_categories_cache';
const CACHE_DURATION = 1000 * 60 * 30; // 30 minutes
const STALE_DURATION = 1000 * 60 * 5; // 5 minutes

interface CacheData {
	categories: Category[];
	timestamp: number;
}

/* =============================================================================
 * Store Implementation
 * ============================================================================= */

export const useTableStore = create<TableStore>((set, get) => ({
	// =========================================================================
	// Initial State
	// =========================================================================

	tableId: null,
	tableTitle: '',
	tableStatus: 'publish',
	source: defaultSource(),
	columns: defaultColumns(),
	settings: defaultSettings(),
	style: defaultStyle(),

	isLoading: false,
	isSaving: false,
	error: null,
	isDirty: false,

	categories: [],
	categoriesLoading: false,
	categoriesLastFetched: null,

	sourceStats: {},
	sourceStatsLoading: {},

	// Legacy state for backward compatibility
	currentStep: 1,
	tableData: {
		title: '',
		source_type: 'all',
		columns: [
			{ id: 'image', label: 'Image' },
			{ id: 'name', label: 'Product Name' },
			{ id: 'price', label: 'Price' },
			{ id: 'add-to-cart', label: 'Buy' },
		],
		config: {},
	},

	// =========================================================================
	// Core Actions
	// =========================================================================

	setTitle: (title) =>
		set({
			tableTitle: title,
			isDirty: true,
			// Also update legacy state for backward compatibility
			tableData: { ...get().tableData, title },
		}),

	setStatus: (status) => set({ tableStatus: status, isDirty: true }),

	// =========================================================================
	// Source Actions
	// =========================================================================

	setSourceType: (type) =>
		set((state) => ({
			source: { ...state.source, type },
			isDirty: true,
			// Also update legacy state
			tableData: { ...state.tableData, source_type: type },
		})),

	setSourceQueryArgs: (args) =>
		set((state) => ({
			source: {
				...state.source,
				queryArgs: { ...state.source.queryArgs, ...args },
			},
			isDirty: true,
			// Also update legacy state for categories/products
			tableData: {
				...state.tableData,
				config: {
					...state.tableData.config,
					...(args.categoryIds !== undefined && {
						categories: args.categoryIds,
					}),
					...(args.postIds !== undefined && {
						products: args.postIds,
					}),
					...(args.productObjects !== undefined && {
						productObjects: args.productObjects,
					}),
				},
			},
		})),

	setSourceSort: (sort) =>
		set((state) => ({
			source: {
				...state.source,
				sort: { ...state.source.sort, ...sort },
			},
			isDirty: true,
		})),

	// =========================================================================
	// Column Actions
	// =========================================================================

	addColumn: (column) =>
		set((state) => ({
			columns: [...state.columns, column],
			isDirty: true,
		})),

	updateColumn: (columnId, updates) =>
		set((state) => ({
			columns: state.columns.map((col) =>
				col.id === columnId ? { ...col, ...updates } : col
			),
			isDirty: true,
		})),

	removeColumn: (columnId) =>
		set((state) => ({
			columns: state.columns.filter((col) => col.id !== columnId),
			isDirty: true,
		})),

	reorderColumns: (sourceIndex, destinationIndex) =>
		set((state) => {
			const newColumns = [...state.columns];
			const [removed] = newColumns.splice(sourceIndex, 1);
			newColumns.splice(destinationIndex, 0, removed);

			// Update order property for each column
			const reorderedColumns = newColumns.map((col, index) => ({
				...col,
				advanced: { ...col.advanced, order: index + 1 },
			}));

			return { columns: reorderedColumns, isDirty: true };
		}),

	// =========================================================================
	// Settings Actions
	// =========================================================================

	setFeatures: (features) =>
		set((state) => ({
			settings: {
				...state.settings,
				features: { ...state.settings.features, ...features },
			},
			isDirty: true,
		})),

	setPagination: (pagination) =>
		set((state) => ({
			settings: {
				...state.settings,
				pagination: { ...state.settings.pagination, ...pagination },
			},
			isDirty: true,
		})),

	setCart: (cart) =>
		set((state) => ({
			settings: {
				...state.settings,
				cart: { ...state.settings.cart, ...cart },
			},
			isDirty: true,
		})),

	setFilters: (filters) =>
		set((state) => ({
			settings: {
				...state.settings,
				filters: { ...state.settings.filters, ...filters },
			},
			isDirty: true,
		})),

	// =========================================================================
	// Style Actions
	// =========================================================================

	setHeaderStyle: (header) =>
		set((state) => ({
			style: {
				...state.style,
				header: { ...state.style.header, ...header },
			},
			isDirty: true,
		})),

	setBodyStyle: (body) =>
		set((state) => ({
			style: {
				...state.style,
				body: { ...state.style.body, ...body },
			},
			isDirty: true,
		})),

	setButtonStyle: (button) =>
		set((state) => ({
			style: {
				...state.style,
				button: { ...state.style.button, ...button },
			},
			isDirty: true,
		})),

	setLayoutStyle: (layout) =>
		set((state) => ({
			style: {
				...state.style,
				layout: { ...state.style.layout, ...layout },
			},
			isDirty: true,
		})),

	setTypographyStyle: (typography) =>
		set((state) => ({
			style: {
				...state.style,
				typography: { ...state.style.typography, ...typography },
			},
			isDirty: true,
		})),

	setHoverStyle: (hover) =>
		set((state) => ({
			style: {
				...state.style,
				hover: { ...state.style.hover, ...hover },
			},
			isDirty: true,
		})),

	setResponsiveStyle: (responsive) =>
		set((state) => ({
			style: {
				...state.style,
				responsive: { ...state.style.responsive, ...responsive },
			},
			isDirty: true,
		})),

	// =========================================================================
	// Persistence Actions
	// =========================================================================

	resetStore: () => {
		const { settings } = require('./settingsStore').useSettingsStore.getState();
		const defaults = settings.table_defaults || {};

		set({
			tableId: null,
			tableTitle: '',
			tableStatus: 'publish',
			// Use global defaults if available, otherwise factory defaults
			source: defaults.source ? { ...defaultSource(), ...defaults.source } : defaultSource(),
			columns:
				defaults.columns && defaults.columns.length > 0
					? defaults.columns
					: defaultColumns(),
			settings: defaults.settings
				? {
					...defaultSettings(),
					...defaults.settings,
					features: {
						...defaultSettings().features,
						...(defaults.settings.features || {}),
					},
					pagination: {
						...defaultSettings().pagination,
						...(defaults.settings.pagination || {}),
					},
					cart: {
						...defaultSettings().cart,
						...(defaults.settings.cart || {}),
					},
					filters: {
						...defaultSettings().filters,
						...(defaults.settings.filters || {}),
					},
				}
				: defaultSettings(),
			style: defaults.style ? { ...defaultStyle(), ...defaults.style } : defaultStyle(),

			isLoading: false,
			isSaving: false,
			error: null,
			isDirty: false,
			currentStep: 1,
			tableData: {
				title: '',
				source_type: 'all',
				columns: [
					{ id: 'image', label: 'Image' },
					{ id: 'name', label: 'Product Name' },
					{ id: 'price', label: 'Price' },
					{ id: 'add-to-cart', label: 'Buy' },
				],
				config: {},
			},
		});
	},

	loadTable: async (id) => {
		set({ isLoading: true, error: null });
		try {
			const data = await apiFetch<ProductTable>(`tables/${id}`);
			get().applyTableData(data);
		} catch (error) {
			set({ error: 'Failed to load table data' });
		} finally {
			set({ isLoading: false });
		}
	},

	applyTableData: (data) => {
		// Map API response to store state
		// Helper to check if source is valid (not empty array)
		const isSourceValid = (s: any) => s && !Array.isArray(s) && typeof s === 'object';
		const isSettingsValid = (s: any) => s && !Array.isArray(s) && typeof s === 'object';
		const isStyleValid = (s: any) => s && !Array.isArray(s) && typeof s === 'object';

		set({
			tableId: data.id || null,
			tableTitle: data.title || '',
			tableStatus: (data.status as TableStatus) || 'private',
			permalink: data.permalink,
			// Use default if source is empty array or invalid
			source: isSourceValid(data.source) ? data.source : defaultSource(),
			columns: data.columns && data.columns.length > 0 ? data.columns : defaultColumns(),
			// Use default if settings is empty array or invalid
			settings: isSettingsValid(data.settings) ? data.settings : defaultSettings(),
			// Use default if style is empty array or invalid
			style: isStyleValid(data.style) ? data.style : defaultStyle(),
			isDirty: false,
			// Also set legacy state
			tableData: {
				title: data.title || '',
				source_type: data.source?.type || 'all',
				columns:
					data.columns?.map((c: Column) => ({
						id: c.id,
						label: c.heading,
					})) || [],
				config: {
					categories: data.source?.queryArgs?.categoryIds || [],
					products: data.source?.queryArgs?.postIds || [],
					productObjects: data.source?.queryArgs?.productObjects || {},
				},
			},
		});
	},

	saveTable: async () => {
		const state = get();
		set({ isSaving: true, error: null });

		try {
			// Clean source queryArgs based on active source type for persistence
			// This ensures we only save relevant data to the DB ("save the one finally remain selected")
			// while keeping the active state in the store during the session ("remember any choices").
			const activeType = state.source.type;
			const cleanedQueryArgs = { ...state.source.queryArgs };

			if (activeType !== 'category') {
				cleanedQueryArgs.categoryIds = [];
			}

			if (activeType !== 'specific') {
				cleanedQueryArgs.postIds = [];
				cleanedQueryArgs.productObjects = {};
			}

			// Build payload matching 4-key backend structure
			const payload: ProductTable = {
				id: state.tableId || undefined,
				title: state.tableTitle,
				status: state.tableStatus,
				source: {
					...state.source,
					queryArgs: cleanedQueryArgs,
				},
				columns: state.columns,
				settings: state.settings,
				style: state.style,
			};

			const response = await apiFetch<ProductTable>('tables', {
				method: 'POST',
				body: JSON.stringify({ data: payload }),
			});

			if (response && response.id) {
				// Use applyTableData logic to ensure the store is perfectly
				// in sync with what was actually persisted (including remapped IDs).
				get().applyTableData(response);
			}

			set({ isDirty: false });
			return true;
		} catch (error) {
			set({ error: 'Failed to save table' });
			return false;
		} finally {
			set({ isSaving: false });
		}
	},

	// =========================================================================
	// Legacy Actions (preserved for backward compatibility)
	// =========================================================================

	setStep: (step) => set({ currentStep: step }),

	setTableData: (data) =>
		set((state) => {
			const newTableData = { ...state.tableData, ...data };

			// Sync to new state structure
			const updates: Partial<TableStore> = { tableData: newTableData };

			if (data.title !== undefined) {
				updates.tableTitle = data.title;
			}

			if (data.source_type !== undefined) {
				updates.source = {
					...state.source,
					type: data.source_type as SourceType,
				};
			}

			if (data.config?.categories !== undefined) {
				updates.source = {
					...(updates.source || state.source),
					queryArgs: {
						...(updates.source?.queryArgs || state.source.queryArgs),
						categoryIds: data.config.categories,
					},
				};
			}

			if (data.config?.products !== undefined) {
				updates.source = {
					...(updates.source || state.source),
					queryArgs: {
						...(updates.source?.queryArgs || state.source.queryArgs),
						postIds: data.config.products,
					},
				};
			}

			updates.isDirty = true;

			return updates as TableStore;
		}),

	// =========================================================================
	// Category Cache Actions (preserved from original)
	// =========================================================================

	preloadCategories: async () => {
		if (get().categoriesLoading) return;

		set({ categoriesLoading: true });

		try {
			const cachedData = localStorage.getItem(CACHE_KEY);
			if (cachedData) {
				const parsed: CacheData = JSON.parse(cachedData);
				const now = Date.now();

				if (now - parsed.timestamp < CACHE_DURATION) {
					set({
						categories: parsed.categories,
						categoriesLastFetched: parsed.timestamp,
						categoriesLoading: false,
					});
					return;
				}
			}

			const data = await apiFetch<Category[]>('categories');
			const timestamp = Date.now();

			const cacheData: CacheData = { categories: data, timestamp };
			localStorage.setItem(CACHE_KEY, JSON.stringify(cacheData));

			set({
				categories: data,
				categoriesLastFetched: timestamp,
				categoriesLoading: false,
			});
		} catch (error) {
			set({ categoriesLoading: false });
		}
	},

	refreshCategoriesIfStale: async () => {
		const { categoriesLastFetched, categoriesLoading } = get();

		if (categoriesLoading || !categoriesLastFetched) return;

		const now = Date.now();
		const isStale = now - categoriesLastFetched > STALE_DURATION;

		if (!isStale) return;

		try {
			const data = await apiFetch<Category[]>('categories');
			const timestamp = Date.now();

			const cacheData: CacheData = { categories: data, timestamp };
			localStorage.setItem(CACHE_KEY, JSON.stringify(cacheData));

			set({
				categories: data,
				categoriesLastFetched: timestamp,
			});
		} catch (error) {
			// Fail silently for background refresh
		}
	},

	forceReloadCategories: async () => {
		set({ categoriesLoading: true });

		try {
			const data = await apiFetch<Category[]>('categories');
			const timestamp = Date.now();

			const cacheData: CacheData = { categories: data, timestamp };
			localStorage.setItem(CACHE_KEY, JSON.stringify(cacheData));

			set({
				categories: data,
				categoriesLastFetched: timestamp,
				categoriesLoading: false,
			});
		} catch (error) {
			set({ categoriesLoading: false });
		}
	},

	// =========================================================================
	// Source Statistics Actions (preserved from original)
	// =========================================================================

	fetchSourceStats: async (sourceType: string) => {
		const { sourceStatsLoading, sourceStats } = get();

		if (sourceStatsLoading[sourceType]) {
			// console.log(`[SourceStats] Already loading ${sourceType}, skipping...`);
			return;
		}

		if (sourceType === 'category' || sourceType === 'specific') return;

		set((state) => ({
			sourceStatsLoading: {
				...state.sourceStatsLoading,
				[sourceType]: true,
			},
		}));

		try {
			// console.log(`[SourceStats] Fetching stats for source type: ${sourceType}`);
			const data = await apiFetch<SourceStats>(`source-stats?type=${sourceType}`);

			set((state) => ({
				sourceStats: {
					...state.sourceStats,
					[sourceType]: data,
				},
				sourceStatsLoading: {
					...state.sourceStatsLoading,
					[sourceType]: false,
				},
			}));

			// console.log(`[SourceStats] Stats loaded for ${sourceType}:`, data);
		} catch (error) {
			console.error(`[SourceStats] Failed to fetch stats for ${sourceType}:`, error);

			set((state) => ({
				sourceStatsLoading: {
					...state.sourceStatsLoading,
					[sourceType]: false,
				},
			}));
		}
	},
}));
