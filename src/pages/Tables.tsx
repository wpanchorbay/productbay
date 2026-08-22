import { apiFetch } from '@/utils/api';
import { ProductTable } from '@/types';
import { Link } from 'react-router-dom';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useToast } from '@/context/ToastContext';
import React, { useEffect, useState } from 'react';
import { Skeleton } from '@/components/ui/Skeleton';
import { useSystemStore } from '@/store/systemStore';
import { useNavigate, useLocation } from 'react-router-dom';
import { ProFeatureGate } from '@/components/ui/ProFeatureGate';
import { useCopyToClipboard } from '@/hooks/useCopyToClipboard';
import { useImportExportStore } from '@/store/importExportStore';
import { WC_PRODUCTS_PATH, NEW_TABLE_PATH } from '@/utils/routes';
import { getTableStatusBadge } from '@/utils/tableStatus';
import {
	DropdownMenu,
	DropdownMenuTrigger,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
} from '@/components/ui/DropdownMenu';
import {
	SearchIcon,
	CopyIcon,
	ChevronLeftIcon,
	ChevronRightIcon,
	FilterIcon,
	XIcon,
	Loader2Icon,
	PlusIcon,
	PackageIcon,
	CheckIcon,
	CopyCheckIcon,
	ArrowUpDownIcon,
	ArrowUpIcon,
	ArrowDownIcon,
	UploadIcon,
	DownloadIcon,
} from 'lucide-react';
import { cn } from '@/utils/cn';

/**
 * Bulk action options for table management
 */

/**
 * Rows per page pagination options
 */
const ROWS_PER_PAGE_OPTIONS = [
	{ label: '10', value: '10' },
	{ label: '20', value: '20' },
	{ label: '50', value: '50' },
	{ label: '100', value: '100' },
	{ label: __('Custom', 'productbay'), value: 'custom' },
];

/**
 * Filter options for table listing
 */
const STATUS_OPTIONS = [
	{ label: __('Published', 'productbay'), value: 'publish' },
	{ label: __('Private', 'productbay'), value: 'private' },
];

const SOURCE_OPTIONS = [
	{ label: __('All Products', 'productbay'), value: 'all' },
	{ label: __('Specific Products', 'productbay'), value: 'specific' },
	{ label: __('Specific Categories', 'productbay'), value: 'category' },
	{ label: __('On Sale', 'productbay'), value: 'sale' },
];

/**
 * Modal state for different actions
 */
interface ModalState {
	type: 'delete' | 'duplicate' | 'toggle' | null;
	tableId: number | null;
	tableName: string;
	currentStatus?: string;
}

/**
 * Tables Page Component
 *
 * Lists all ProductBay tables with search, filter, bulk actions, and pagination.
 *
 * @since 1.0.0
 */
const Tables = () => {
	const [tables, setTables] = useState<ProductTable[]>([]);
	const [isLoading, setIsLoading] = useState(true);

	// UI States
	const [searchQuery, setSearchQuery] = useState('');
	const [filterStatuses, setFilterStatuses] = useState<string[]>([]);
	const [filterSources, setFilterSources] = useState<string[]>([]);
	const [selectedRows, setSelectedRows] = useState<number[]>([]);
	const [selectedBulkAction, setSelectedBulkAction] = useState('');
	const [currentPage, setCurrentPage] = useState(1);
	const [jumpPage, setJumpPage] = useState('');
	const [itemsPerPage, setItemsPerPage] = useState(10);
	const [isCustomPerPage, setIsCustomPerPage] = useState(false);
	const [sortConfig, setSortConfig] = useState<{
		key: string;
		direction: 'asc' | 'desc';
	} | null>({ key: 'date', direction: 'desc' });

	// Use the system store
	const { status, loading, fetchStatus } = useSystemStore();
	const { openImportModal, openExportModal } = useImportExportStore();

	useEffect(() => {
		// Fetch fresh data on mount (background update if data exists)
		fetchStatus();
	}, [fetchStatus]);

	// Modal state
	const [modalState, setModalState] = useState<ModalState>({
		type: null,
		tableId: null,
		tableName: '',
	});

	// Action loading state - tracks which table is being acted upon
	const [actionLoading, setActionLoading] = useState<Record<number, string>>({});

	// Toast hook
	const { toast } = useToast();

	const location = useLocation();

	// Pro Gate State
	const [showProGate, setShowProGate] = useState(false);
	const isPro = !!(window as any).productBaySettings?.proVersion;

	/**
	 * Bulk action options for table management
	 */
	const bulkOptions = [
		{ label: __('Delete', 'productbay'), value: 'delete' },
		{ label: __('Set Published', 'productbay'), value: 'published' },
		{ label: __('Set Private', 'productbay'), value: 'private' },
		{
			label: isPro
				? __('Export Selected', 'productbay')
				: __('Export Selected (PRO)', 'productbay'),
			value: 'export',
		},
	];

	useEffect(() => {
		loadTables();
	}, [location.state?.refresh]);

	const loadTables = async () => {
		try {
			const data = await apiFetch<ProductTable[]>('tables');
			setTables(data);
		} catch (error) {
			console.error(error);
			toast({
				title: __('Error', 'productbay'),
				description: __('Failed to load tables', 'productbay'),
				type: 'error',
			});
		} finally {
			setIsLoading(false);
		}
	};

	// Open delete modal
	const openDeleteModal = (id: number, title: string) => {
		setModalState({ type: 'delete', tableId: id, tableName: title });
	};

	// Open duplicate modal
	const openDuplicateModal = (id: number, title: string) => {
		setModalState({ type: 'duplicate', tableId: id, tableName: title });
	};

	// Open toggle status modal
	const openToggleModal = (id: number, title: string, currentStatus: string) => {
		setModalState({
			type: 'toggle',
			tableId: id,
			tableName: title,
			currentStatus,
		});
	};

	// Close modal
	const closeModal = () => {
		setModalState({ type: null, tableId: null, tableName: '' });
	};

	// Handle delete action
	const handleDelete = async () => {
		if (!modalState.tableId) return;

		const id = modalState.tableId;
		setActionLoading((prev) => ({ ...prev, [id]: 'delete' }));
		closeModal();

		try {
			await apiFetch(`tables/${id}`, { method: 'DELETE' });
			setTables((prev) => prev.filter((t) => t.id !== id));
			toast({
				title: __('Success', 'productbay'),
				description: __('Table deleted successfully', 'productbay'),
				type: 'success',
			});
		} catch (error) {
			console.error(error);
			toast({
				title: __('Error', 'productbay'),
				description: __('Failed to delete table', 'productbay'),
				type: 'error',
			});
		} finally {
			setActionLoading((prev) => {
				const newState = { ...prev };
				delete newState[id];
				return newState;
			});
		}
	};

	// Handle duplicate action
	const handleDuplicate = async () => {
		if (!modalState.tableId) return;

		const id = modalState.tableId;
		const tableToClone = tables.find((t) => t.id === id);
		if (!tableToClone) return;

		setActionLoading((prev) => ({ ...prev, [id]: 'duplicate' }));
		closeModal();

		try {
			// Create payload with duplicated data
			const payload = {
				title: `${tableToClone.title} (Copy)`,
				status: 'private', // New duplicates start as private
				source: tableToClone.source || {},
				columns: tableToClone.columns || [],
				settings: tableToClone.settings || {},
				style: tableToClone.style || {},
			};

			const newTable = await apiFetch<ProductTable>('tables', {
				method: 'POST',
				body: JSON.stringify({ data: payload }),
			});

			setTables((prev) => [newTable, ...prev]);
			toast({
				title: __('Success', 'productbay'),
				description: __('Table duplicated successfully', 'productbay'),
				type: 'success',
			});
		} catch (error) {
			console.error(error);
			toast({
				title: __('Error', 'productbay'),
				description: __('Failed to duplicate table', 'productbay'),
				type: 'error',
			});
		} finally {
			setActionLoading((prev) => {
				const newState = { ...prev };
				delete newState[id];
				return newState;
			});
		}
	};

	// Handle toggle published/private
	const handleToggleActive = async () => {
		if (!modalState.tableId) return;

		const id = modalState.tableId;
		const table = tables.find((t) => t.id === id);
		if (!table) return;

		const newStatus = table.status === 'publish' ? 'private' : 'publish';
		setActionLoading((prev) => ({ ...prev, [id]: 'toggle' }));
		closeModal();

		try {
			const payload = {
				id: table.id,
				title: table.title,
				status: newStatus,
				source: table.source || {},
				columns: table.columns || [],
				settings: table.settings || {},
				style: table.style || {},
			};

			await apiFetch('tables', {
				method: 'POST',
				body: JSON.stringify({ data: payload }),
			});

			setTables((prev) => prev.map((t) => (t.id === id ? { ...t, status: newStatus } : t)));

			toast({
				title: __('Success', 'productbay'),
				description:
					newStatus === 'publish'
						? __('Table published successfully', 'productbay')
						: __('Table set to private successfully', 'productbay'),
				type: 'success',
			});
		} catch (error) {
			console.error(error);
			toast({
				title: __('Error', 'productbay'),
				description: __('Failed to update table status', 'productbay'),
				type: 'error',
			});
		} finally {
			setActionLoading((prev) => {
				const newState = { ...prev };
				delete newState[id];
				return newState;
			});
		}
	};

	const { copy: copyToClipboard } = useCopyToClipboard();
	const [copiedTableId, setCopiedTableId] = useState<number | null>(null);

	const copyShortcode = (shortcode: string, id: number) => {
		copyToClipboard(shortcode);
		setCopiedTableId(id);
		setTimeout(
			() => setCopiedTableId((currentId) => (currentId === id ? null : currentId)),
			2000
		);
	};

	// Bulk Actions
	const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
		if (e.target.checked) {
			setSelectedRows(currentTables.map((t) => t.id!).filter(Boolean));
		} else {
			setSelectedRows([]);
		}
	};

	const handleSelectRow = (id: number) => {
		if (selectedRows.includes(id)) {
			setSelectedRows(selectedRows.filter((rowId) => rowId !== id));
		} else {
			setSelectedRows([...selectedRows, id]);
		}
	};

	const handleBulkAction = async () => {
		if (!selectedBulkAction || selectedRows.length === 0) return;

		setIsLoading(true);

		try {
			// Process actions
			if (selectedBulkAction === 'delete') {
				// Delete all selected tables
				await Promise.all(
					selectedRows.map((id) => apiFetch(`tables/${id}`, { method: 'DELETE' }))
				);

				// Update local state
				setTables((prev) => prev.filter((t) => t.id !== undefined && !selectedRows.includes(t.id)));

				toast({
					title: __('Success', 'productbay'),
					description: sprintf(
						/* translators: %d: number of tables deleted */
						_n(
							'Deleted %d table successfully',
							'Deleted %d tables successfully',
							selectedRows.length,
							'productbay'
						),
						selectedRows.length
					),
					type: 'success',
				});
			} else if (selectedBulkAction === 'published' || selectedBulkAction === 'private') {
				// Determine new status
				const newStatus = selectedBulkAction === 'published' ? 'publish' : 'private';

				// Process updates for each selected table
				await Promise.all(
					selectedRows.map(async (id) => {
						const table = tables.find((t) => t.id === id);
						if (!table) return;

						// Only update if status is different
						if (table.status === newStatus) return;

						const payload = {
							id: table.id,
							title: table.title,
							status: newStatus,
							source: table.source || {},
							columns: table.columns || [],
							settings: table.settings || {},
							style: table.style || {},
						};

						await apiFetch('tables', {
							method: 'POST',
							body: JSON.stringify({ data: payload }),
						});
					})
				);

				// Update local state
				setTables((prev) =>
					prev.map((t) => (t.id !== undefined && selectedRows.includes(t.id) ? { ...t, status: newStatus } : t))
				);

				toast({
					title: __('Success', 'productbay'),
					description: sprintf(
						/* translators: %d: number of tables updated */
						_n(
							'Updated status for %d table',
							'Updated status for %d tables',
							selectedRows.length,
							'productbay'
						),
						selectedRows.length
					),
					type: 'success',
				});
			} else if (selectedBulkAction === 'export') {
				handleExport();
			}
		} catch (error) {
			console.error(error);
			toast({
				title: __('Error', 'productbay'),
				description: __('Failed to apply bulk action', 'productbay'),
				type: 'error',
			});
		} finally {
			setIsLoading(false);
			if (selectedBulkAction !== 'export') {
				setSelectedRows([]);
			}
			setSelectedBulkAction('');
		}
	};

	/**
	 * Handle individual and bulk export
	 */
	const handleExport = (ids?: number[]) => {
		if (!isPro) {
			setShowProGate(true);
			return;
		}
		// Use provided IDs or current selection
		const exportIds = ids || selectedRows;
		openExportModal(tables, exportIds);
	};

	/**
	 * Handle table import with pro gate
	 */
	const handleImport = () => {
		if (!isPro) {
			setShowProGate(true);
			return;
		}
		openImportModal();
	};

	// Filtering & Pagination	// Derived state
	const filteredTables = React.useMemo(() => {
		return tables.filter((table) => {
			const matchesSearch =
				table.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
				table.id?.toString() === searchQuery.replace('#', '') ||
				table.shortcode?.toLowerCase().includes(searchQuery.toLowerCase());

			const matchesStatus =
				filterStatuses.length === 0 || filterStatuses.includes(table.status);

			// Resolve the actual source type
			const sourceType =
				typeof table.source === 'object' && table.source !== null
					? table.source.type || 'all'
					: table.source || 'all';

			const matchesSource = filterSources.length === 0 || filterSources.includes(sourceType);

			return matchesSearch && matchesStatus && matchesSource;
		});
	}, [tables, searchQuery, filterStatuses, filterSources]);

	const sortedTables = React.useMemo(() => {
		const sortableTables = [...filteredTables];
		if (sortConfig !== null) {
			sortableTables.sort((a, b) => {
				let aValue: any = a[sortConfig.key as keyof ProductTable];
				let bValue: any = b[sortConfig.key as keyof ProductTable];

				if (sortConfig.key === 'date') {
					aValue = new Date((a.date || '').replace(' ', 'T')).getTime();
					bValue = new Date((b.date || '').replace(' ', 'T')).getTime();
				} else if (sortConfig.key === 'source') {
					aValue =
						typeof a.source === 'object' && a.source !== null
							? a.source.type || 'all'
							: a.source || 'all';
					bValue =
						typeof b.source === 'object' && b.source !== null
							? b.source.type || 'all'
							: b.source || 'all';
				} else if (typeof aValue === 'string') {
					aValue = aValue.toLowerCase();
					bValue = bValue.toLowerCase();
				}

				if (aValue < bValue) {
					return sortConfig.direction === 'asc' ? -1 : 1;
				}
				if (aValue > bValue) {
					return sortConfig.direction === 'asc' ? 1 : -1;
				}
				return 0;
			});
		}
		return sortableTables;
	}, [filteredTables, sortConfig]);

	const totalPages = Math.ceil(sortedTables.length / itemsPerPage);
	const currentTables = sortedTables.slice(
		(currentPage - 1) * itemsPerPage,
		currentPage * itemsPerPage
	);

	const handleSort = (key: string) => {
		let direction: 'asc' | 'desc' = 'asc';
		if (sortConfig && sortConfig.key === key && sortConfig.direction === 'asc') {
			direction = 'desc';
		}
		setSortConfig({ key, direction });
	};

	const SortIcon = ({ columnKey }: { columnKey: string }) => {
		if (sortConfig?.key !== columnKey) {
			return (
				<ArrowUpDownIcon className="w-4 h-4 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity ml-1" />
			);
		}
		return sortConfig.direction === 'asc' ? (
			<ArrowUpIcon className="w-4 h-4 text-blue-600 ml-1" />
		) : (
			<ArrowDownIcon className="w-4 h-4 text-blue-600 ml-1" />
		);
	};

	return (
		<div className="space-y-6">
			{/* 
				Empty State Products: No Published Products Found
				Shown when the site has zero 'published' WooCommerce products.
				ProductBay requires published products to build and display tables.
			*/}
			{!isLoading && status?.product_count === 0 && (
				<div className="bg-white p-6 rounded-xl flex flex-col md:flex-row items-center gap-6 mb-6 shadow-sm border border-orange-500">
					<div className="p-4 rounded-2xl text-orange-500 shrink-0">
						<PackageIcon size={32} />
					</div>
					<div className="flex-1 text-center md:text-left">
						<h3 className="text-lg font-bold text-red-950 m-0 pb-1">
							{__('No Published Products Found', 'productbay')}
						</h3>
						<p className="text-gray-600 text-sm max-w-2xl">
							{__(
								'ProductBay requires published WooCommerce products to build your tables.',
								'productbay'
							)}
							<br />
							{__(
								"We couldn't find any products in your WooCommerce store yet.",
								'productbay'
							)}
							<br />
							{__(
								'Create your first product to start building high-converting tables.',
								'productbay'
							)}
						</p>
					</div>
					<div className="shrink-0">
						<Button
							variant="secondary"
							className="cursor-pointer border border-green-500"
							onClick={() => window.open(WC_PRODUCTS_PATH, '_blank')}
						>
							<PlusIcon size={18} className="mr-2" />
							{__('Add Products', 'productbay')}
						</Button>
					</div>
				</div>
			)}

			{/* Header */}
			<div className="flex justify-between items-center">
				{/* Page Title */}
				<h1 className="text-2xl font-bold text-gray-800 m-0">
					<span className="mr-1">{__('All Tables', 'productbay')}</span>
					{loading ? (
						<span className="animate-pulse font-medium">(*)</span>
					) : (
						<span className="font-medium text-gray-600">({status?.table_count})</span>
					)}
				</h1>

				<div className="flex gap-2">
					<Button
						variant="secondary"
						size="sm"
						onClick={handleImport}
						className="flex-1 flex items-center justify-center gap-2 cursor-pointer px-3 py-2 border border-gray-200 rounded text-sm bg-white hover:bg-blue-500 hover:text-white disabled:opacity-50 disabled:cursor-not-allowed"
					>
						<UploadIcon size={14} /> {__('Import', 'productbay')}
					</Button>

					<Button
						variant="secondary"
						size="sm"
						onClick={() => handleExport()}
						className="flex-1 flex items-center justify-center gap-2 cursor-pointer px-3 py-2 border border-gray-200 rounded text-sm bg-white hover:bg-blue-500 hover:text-white disabled:opacity-50 disabled:cursor-not-allowed"
						disabled={isLoading || tables.length === 0}
					>
						<DownloadIcon size={14} /> {__('Export', 'productbay')}
					</Button>
				</div>
			</div>

			{/* Top Menu: Bulk Actions & Search/Filter */}
			<div className="flex flex-col sm:flex-row justify-between items-center gap-4 py-2">
				{/* Left: Bulk Actions */}
				<div className="flex items-center gap-2 w-full sm:w-auto">
					<div className="w-48">
						<Select
							placeholder={__('Bulk Actions', 'productbay')}
							label={__('Actions', 'productbay')}
							allowDeselect={true}
							options={bulkOptions}
							value={selectedBulkAction}
							onChange={setSelectedBulkAction}
						/>
					</div>
					<Button
						variant="default"
						className="px-3 py-2 text-sm disabled:opacity-50 font-medium h-10"
						disabled={selectedRows.length === 0 || !selectedBulkAction}
						onClick={handleBulkAction}
					>
						{__('Apply', 'productbay')}
					</Button>
					{selectedRows.length > 0 && (
						<span className="text-sm text-gray-500 ml-2">
							{sprintf(
								/* translators: %d: number of selected items */
								__('%d items selected', 'productbay'),
								selectedRows.length
							)}
						</span>
					)}
				</div>

				{/* Right: Search & Filter */}
				<div className="flex items-center gap-2 w-full sm:w-auto">
					{/* Search */}
					<div className="relative w-full sm:w-64">
						<Input
							type="text"
							placeholder={__('Search tables...', 'productbay')}
							className="pr-9"
							value={searchQuery}
							onChange={(e) => setSearchQuery(e.target.value)}
						/>
						{searchQuery ? (
							<button
								onClick={() => setSearchQuery('')}
								className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1 bg-transparent rounded hover:bg-gray-200 cursor-pointer flex items-center justify-center"
							>
								<XIcon size={16} />
							</button>
						) : (
							<SearchIcon
								size={16}
								className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
							/>
						)}
					</div>

					{/* Filter */}
					<div>
						<DropdownMenu>
							<DropdownMenuTrigger asChild>
								<Button
									variant="outline"
									className="gap-2 bg-white hover:bg-blue-500 hover:text-white relative border-gray-300 cursor-pointer group"
								>
									<FilterIcon className="w-4 h-4 text-gray-500 group-hover:text-white" />
									{__('Filter', 'productbay')}
									{(filterStatuses.length > 0 || filterSources.length > 0) && (
										<span className="absolute -top-2 -right-2 bg-blue-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
											{filterStatuses.length + filterSources.length}
										</span>
									)}
								</Button>
							</DropdownMenuTrigger>
							<DropdownMenuContent align="end" className="w-56 p-2">
								<DropdownMenuLabel>{__('Status', 'productbay')}</DropdownMenuLabel>
								{STATUS_OPTIONS.map((option) => (
									<DropdownMenuItem
										key={option.value}
										closeOnSelect={false}
										onClick={(e) => {
											setFilterStatuses((prev) =>
												prev.includes(option.value)
													? prev.filter((v) => v !== option.value)
													: [...prev, option.value]
											);
										}}
									>
										<div className="flex items-center w-full justify-between">
											<span>{option.label}</span>
											{filterStatuses.includes(option.value) && (
												<CheckIcon className="w-4 h-4 text-blue-600" />
											)}
										</div>
									</DropdownMenuItem>
								))}

								<DropdownMenuSeparator />

								<DropdownMenuLabel>{__('Source', 'productbay')}</DropdownMenuLabel>
								{SOURCE_OPTIONS.map((option) => (
									<DropdownMenuItem
										key={option.value}
										closeOnSelect={false}
										onClick={(e) => {
											setFilterSources((prev) =>
												prev.includes(option.value)
													? prev.filter((v) => v !== option.value)
													: [...prev, option.value]
											);
										}}
									>
										<div className="flex items-center w-full justify-between">
											<span>{option.label}</span>
											{filterSources.includes(option.value) && (
												<CheckIcon className="w-4 h-4 text-blue-600" />
											)}
										</div>
									</DropdownMenuItem>
								))}

								{(filterStatuses.length > 0 || filterSources.length > 0) && (
									<>
										<DropdownMenuSeparator />
										<DropdownMenuItem
											className="text-red-600 focus:text-red-700 justify-center font-medium mt-1"
											onClick={(e) => {
												setFilterStatuses([]);
												setFilterSources([]);
											}}
										>
											{__('Clear Filters', 'productbay')}
										</DropdownMenuItem>
									</>
								)}
							</DropdownMenuContent>
						</DropdownMenu>
					</div>
				</div>
			</div>

			{/* Table */}
			<div className="bg-white rounded-lg shadow-xs border border-gray-200 overflow-hidden">
				<table className="w-full text-left border-collapse">
					<thead className="bg-gray-50 border-b border-gray-200">
						<tr>
							<th className="px-4 py-4 w-10 text-center">
								<input
									type="checkbox"
									className="rounded border border-gray-400/70 bg-wp-bg text-blue-600 focus:ring-blue-500"
									checked={
										currentTables.length > 0 &&
										selectedRows.length === currentTables.length
									}
									onChange={handleSelectAll}
								/>
							</th>
							<th
								className="p-4 text-sm font-bold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 group transition-colors select-none"
								onClick={() => handleSort('title')}
							>
								<div className="flex items-center">
									{__('Title', 'productbay')}
									<SortIcon columnKey="title" />
								</div>
							</th>
							<th
								className="p-4 text-sm font-bold text-gray-500 uppercase tracking-wider w-24 cursor-pointer hover:bg-gray-100 group transition-colors select-none"
								onClick={() => handleSort('status')}
							>
								<div className="flex items-center">
									{__('Status', 'productbay')}
									<SortIcon columnKey="status" />
								</div>
							</th>
							<th className="p-4 text-sm font-bold text-gray-500 uppercase tracking-wider">
								{__('Shortcode', 'productbay')}
							</th>
							<th
								className="p-4 text-sm font-bold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 group transition-colors select-none"
								onClick={() => handleSort('source')}
							>
								<div className="flex items-center">
									{__('Product Source', 'productbay')}
									<SortIcon columnKey="source" />
								</div>
							</th>
							<th
								className="p-4 text-sm font-bold text-gray-500 uppercase tracking-wider w-32 cursor-pointer hover:bg-gray-100 group transition-colors select-none"
								onClick={() => handleSort('date')}
							>
								<div className="flex items-center">
									{__('Date', 'productbay')}
									<SortIcon columnKey="date" />
								</div>
							</th>
						</tr>
					</thead>
					<tbody className="divide-y divide-gray-200">
						{isLoading ? (
							Array.from({ length: 5 }).map((_, i) => (
								<tr key={i}>
									<td className="px-4 py-4 text-center">
										<Skeleton className="h-4 w-4 rounded mx-auto" />
									</td>
									<td className="p-4">
										<div className="space-y-2">
											<Skeleton className="h-5 w-48" />
										</div>
									</td>
									<td className="p-4">
										<Skeleton className="h-6 w-20 rounded-full" />
									</td>
									<td className="p-4">
										<Skeleton className="h-8 w-24 rounded" />
									</td>
									<td className="p-4">
										<Skeleton className="h-6 w-32 rounded-full" />
									</td>
									<td className="p-4">
										<div className="space-y-1">
											<Skeleton className="h-4 w-24" />
											<Skeleton className="h-3 w-20" />
										</div>
									</td>
								</tr>
							))
						) : currentTables.length === 0 ? (
							<tr>
								<td colSpan={6} className="px-6 py-12 text-center text-gray-400">
									{/* Show welcome screen if no tables exist at all */}
									{tables.length === 0 ? (
										<EmptyStateTables />
									) : (
										/* Show not found message if tables exist but are filtered out */
										<div className="flex flex-col items-center justify-center py-8">
											<div className="bg-gray-50 rounded-full p-4 mb-4">
												<SearchIcon size={32} className="text-gray-400" />
											</div>
											<h3 className="text-lg font-medium text-gray-900 mb-1">
												{__('No tables found', 'productbay')}
											</h3>
											<p className="text-gray-500 text-sm max-w-sm text-center mb-6">
												{__(
													'Your search query or filters did not match any tables. Try adjusting your search term or filters.',
													'productbay'
												)}
											</p>
											<Button
												variant="outline"
												onClick={() => {
													setSearchQuery('');
													setFilterStatuses([]);
													setFilterSources([]);
												}}
												className="cursor-pointer"
											>
												{__('Clear Search & Filters', 'productbay')}
											</Button>
										</div>
									)}
								</td>
							</tr>
						) : (
							currentTables.map((table) => {
								if (table.id === undefined) return null;
								const isActing = !!actionLoading[table.id];
								const actionType = actionLoading[table.id];

								return (
									// Table Row
									<tr
										key={table.id}
										className={`group hover:bg-productbay-brand/7 border-l-2 border-transparent hover:border-blue-300 transition-colors ${isActing ? 'opacity-50' : ''
											}`}
									>
										{/* Checkbox */}
										<td className="px-4 py-4 text-center">
											<input
												type="checkbox"
												className="rounded border border-gray-400/70 bg-white text-blue-600 focus:ring-blue-500"
												checked={selectedRows.includes(table.id!)}
												onChange={() => handleSelectRow(table.id!)}
												disabled={isActing}
											/>
										</td>
										<td className="p-4 relative">
											<div className="font-medium text-wp-text text-base flex items-center gap-2">
												<Link
													to={`/table/${table.id}`}
													className="hover:text-blue-600"
												>
													{table.title}
													<span className="ml-2 text-sm font-normal text-gray-500">
														({table.columns?.length || 0}{' '}
														{__('cols', 'productbay')})
													</span>
													{isActing && (
														<Loader2Icon className="w-4 h-4 inline-block ml-2 animate-spin" />
													)}
												</Link>
											</div>

											{/* Hover Actions (Visible on group hover) */}
											<div className="flex items-center gap-2 mt-1 text-xs opacity-0 group-hover:opacity-100 transition-opacity">
												<Link
													to={`/table/${table.id}`}
													className="text-blue-600 hover:underline underline-offset-4 bg-transparent cursor-pointer"
												>
													{__('Edit', 'productbay')}
												</Link>
												<span className="text-gray-300">|</span>
												<button
													onClick={() =>
														openDuplicateModal(table.id!, table.title)
													}
													className="text-blue-600 hover:underline underline-offset-4 bg-transparent cursor-pointer"
													disabled={isActing}
												>
													{__('Duplicate', 'productbay')}
												</button>
												<span className="text-gray-300">|</span>
												<button
													onClick={() =>
														openToggleModal(
															table.id!,
															table.title,
															table.status
														)
													}
													className="text-blue-600 hover:underline underline-offset-4 bg-transparent cursor-pointer"
													disabled={isActing}
												>
													{table.status === 'publish'
														? __('Set Private', 'productbay')
														: __('Publish', 'productbay')}
												</button>
												<span className="text-gray-300">|</span>
												{table.permalink ? (
													<a
														href={table.permalink}
														target="_blank"
														rel="noreferrer"
														className="text-blue-600 hover:underline underline-offset-4 bg-transparent cursor-pointer"
													>
														{__('Preview', 'productbay')}
													</a>
												) : (
													<span className="text-gray-400 cursor-not-allowed">
														{__('Preview', 'productbay')}
													</span>
												)}
												<span className="text-gray-300">|</span>
												<button
													onClick={() =>
														openDeleteModal(table.id!, table.title)
													}
													className="text-red-600 hover:underline underline-offset-4 bg-transparent cursor-pointer"
													disabled={isActing}
												>
													{__('Delete', 'productbay')}
												</button>
											</div>
										</td>
										{/* Status Badge */}
										<td className="p-4">
											<span
												className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getTableStatusBadge(table.status).badgeClassName}`}
											>
												{getTableStatusBadge(table.status).label}
											</span>
										</td>
										{/* Shortcode */}
										<td className="p-4">
											<div className="flex flex-col items-start gap-2 max-w-[280px]">
												<div className="bg-gray-100 inline-flex text-gray-600 px-3 py-1 rounded border border-gray-300 items-center gap-1">
													<span className="select-all font-mono text-sm bg-transparent p-1 hover:bg-gray-200 truncate">
														{table.shortcode || ''}
													</span>
													<Button
														variant="outline"
														size="xs"
														onClick={() =>
															copyShortcode(table.shortcode || '', table.id!)
														}
														title={
															copiedTableId === table.id
																? __('Copied!', 'productbay')
																: __('Copy shortcode', 'productbay')
														}
														className={`cursor-pointer py-1 px-1.5 ml-1 transition-colors flex-shrink-0 ${copiedTableId === table.id
															? 'bg-green-50 border-green-200 text-green-600 hover:bg-green-100 hover:text-green-700'
															: 'bg-transparent hover:bg-white text-gray-600'
															}`}
														disabled={isActing}
													>
														{copiedTableId === table.id ? (
															<CopyCheckIcon className="w-4 h-4" />
														) : (
															<CopyIcon className="w-4 h-4" />
														)}
													</Button>
												</div>
												{/* Permalink removed from here, now in hover actions */}
											</div>
										</td>
										{/* Product Source & Count Badge */}
										<td className="p-4 text-sm text-gray-600 capitalize">
											<div className="flex items-center gap-2">
												<span>
													{typeof table.source === 'object' &&
														table.source !== null
														? // @ts-ignore
														table.source.type || 'Custom'
														: table.source || 'WooCommerce'}
												</span>
												{table.productCount !== undefined && (
													<span
														className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200"
														title={__(
															'Matching Products',
															'productbay'
														)}
													>
														{table.productCount}
													</span>
												)}
											</div>
										</td>
										{/* Date (Created & Modified) */}
										<td className="p-4 whitespace-nowrap text-sm text-gray-500">
											{table.date && (
												<div className="flex flex-col">
													{table.status === 'publish' ? (
														<span className="font-medium text-xs text-gray-700">
															{__('Published', 'productbay')}: &nbsp;
															{new Date(
																table.date.replace(' ', 'T')
															).toLocaleDateString(undefined, {
																year: 'numeric',
																month: 'short',
																day: 'numeric',
															})}
														</span>
													) : (
														<span className="font-medium text-xs text-gray-700">
															{__('Created', 'productbay')}: &nbsp;
															{new Date(
																table.date.replace(' ', 'T')
															).toLocaleDateString(undefined, {
																year: 'numeric',
																month: 'short',
																day: 'numeric',
															})}
														</span>
													)}
													{table.modifiedDate &&
														table.modifiedDate !== table.date && (
															<span
																className="text-xs text-gray-400 mt-1"
																title={table.modifiedDate}
															>
																{__('Modified', 'productbay')}:
																&nbsp;
																{new Date(
																	table.modifiedDate.replace(
																		' ',
																		'T'
																	)
																).toLocaleDateString(undefined, {
																	year: 'numeric',
																	month: 'short',
																	day: 'numeric',
																})}
															</span>
														)}
												</div>
											)}
										</td>
									</tr>
								);
							})
						)}
					</tbody>
				</table>
			</div>

			{/* Pagination Menu */}
			<div className="flex justify-between items-center">
				{/* Pagination Info */}
				{(filteredTables.length > 0) &&
					<div className="text-sm text-gray-500 px-1">
						{sprintf(
							/* translators: %1$d: start index, %2$d: end index, %3$d: total entries */
							__('Showing %1$d to %2$d of %3$d entries', 'productbay'),
							(currentPage - 1) * itemsPerPage + 1,
							Math.min(currentPage * itemsPerPage, filteredTables.length),
							filteredTables.length
						)}
					</div>
				}
				{/* Pagination Controls */}
				<div className="flex items-center gap-4 bg-white px-4 py-2 rounded-lg shadow-xs border border-gray-200">
					{/* Rows Per Page */}
					<div className="flex items-center justify-center gap-2 h-8 pr-4 border-r border-gray-200 ">
						<span className="text-sm text-gray-500 whitespace-nowrap hidden sm:inline">
							{__('Rows per page:', 'productbay')}
						</span>
						{isCustomPerPage ? (
							<div className="relative w-26">
								<Input
									type="number"
									min={1}
									className="h-8 pr-6 text-xs"
									value={itemsPerPage}
									onChange={(e) => {
										const val = parseInt(e.target.value);
										if (!isNaN(val) && val > 0) {
											setItemsPerPage(val);
											setCurrentPage(1);
										} else if (e.target.value === '') {
											setItemsPerPage(0);
										}
									}}
								/>
								<button
									onClick={() => {
										setItemsPerPage(10);
										setIsCustomPerPage(false);
										setCurrentPage(1);
									}}
									className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
								>
									<XIcon className="w-3 h-3" />
								</button>
							</div>
						) : (
							<div className="w-26">
								<Select
									size="xs"
									options={ROWS_PER_PAGE_OPTIONS}
									value={isCustomPerPage ? 'custom' : itemsPerPage.toString()}
									onChange={(val: string) => {
										if (val === 'custom') {
											setIsCustomPerPage(true);
										} else {
											setIsCustomPerPage(false);
											setItemsPerPage(Number(val));
											setCurrentPage(1);
										}
									}}
								/>
							</div>
						)}
					</div>

					<div className="flex items-center gap-2">
						{/* Previous Page Button */}
						<Button
							size="xs"
							variant="outline"
							onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
							disabled={currentPage === 1}
						>
							<ChevronLeftIcon size={16} />
						</Button>
						{/* Page Number Buttons */}
						{Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
							<Button
								key={page}
								size="xs"
								variant={currentPage === page ? 'default' : 'outline'}
								disabled={currentPage === page}
								onClick={() => setCurrentPage(page)}
							>
								{page}
							</Button>
						))}
						{/* Next Page Button */}
						<Button
							size="xs"
							variant="outline"
							onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
							disabled={currentPage === totalPages}
						>
							<ChevronRightIcon size={16} />
						</Button>
					</div>

					{/* Go to Page */}
					<div className="flex items-center gap-2 border-l border-gray-200 pl-4 h-8">
						<span className="text-sm text-gray-500 whitespace-nowrap">
							{__('Go to page', 'productbay')}
						</span>
						<Input
							type="number"
							min={1}
							max={totalPages}
							className="w-16 h-8 text-xs px-2"
							value={jumpPage}
							onChange={(e) => setJumpPage(e.target.value)}
							onKeyDown={(e) => {
								if (e.key === 'Enter') {
									const page = parseInt(jumpPage);
									if (!isNaN(page) && page >= 1 && page <= totalPages) {
										setCurrentPage(page);
										setJumpPage('');
									}
								}
							}}
							placeholder="#"
						/>
					</div>
				</div>
			</div>

			{/* Delete Confirmation Modal */}
			<Modal
				isOpen={modalState.type === 'delete'}
				onClose={closeModal}
				title={__('Delete Table?', 'productbay')}
				maxWidth="md"
				primaryButton={{
					text: __('Delete', 'productbay'),
					onClick: handleDelete,
					variant: 'danger',
				}}
				secondaryButton={{
					text: __('Cancel', 'productbay'),
					onClick: closeModal,
					variant: 'secondary',
				}}
			>
				<p className="text-gray-700">
					{sprintf(
						/* translators: %s: table name */
						__(
							'Are you sure you want to delete "%s"? This action cannot be undone.',
							'productbay'
						),
						modalState.tableName
					)}
				</p>
			</Modal>

			{/* Duplicate Confirmation Modal */}
			<Modal
				isOpen={modalState.type === 'duplicate'}
				onClose={closeModal}
				title={__('Duplicate Table?', 'productbay')}
				maxWidth="md"
				primaryButton={{
					text: __('Duplicate', 'productbay'),
					onClick: handleDuplicate,
					variant: 'primary',
				}}
				secondaryButton={{
					text: __('Cancel', 'productbay'),
					onClick: closeModal,
					variant: 'secondary',
				}}
			>
				<p className="text-gray-700">
					{sprintf(
						/* translators: %s: table name */
						__('Create a copy of "%s"?', 'productbay'),
						modalState.tableName
					)}
				</p>
			</Modal>

			{/* Toggle Status Confirmation Modal */}
			<Modal
				isOpen={modalState.type === 'toggle'}
				onClose={closeModal}
				title={__('Change Table Status?', 'productbay')}
				maxWidth="md"
				primaryButton={{
					text:
						modalState.currentStatus === 'publish'
							? __('Set Private', 'productbay')
							: __('Publish', 'productbay'),
					onClick: handleToggleActive,
					variant: modalState.currentStatus === 'publish' ? 'danger' : 'primary',
				}}
				secondaryButton={{
					text: __('Cancel', 'productbay'),
					onClick: closeModal,
					variant: 'secondary',
				}}
			>
				<p className="text-gray-700">
					{modalState.currentStatus === 'publish'
						? sprintf(
							/* translators: %s: table name */
							__('Are you sure you want to set "%s" to private?', 'productbay'),
							modalState.tableName
						)
						: sprintf(
							/* translators: %s: table name */
							__('Are you sure you want to publish "%s"?', 'productbay'),
							modalState.tableName
						)}
				</p>
			</Modal>

			{/* Pro Feature Gate for programmatic trigger */}
			<ProFeatureGate
				featureName={__('Export Tables', 'productbay')}
				isOpen={showProGate}
				onClose={() => setShowProGate(false)}
			>
				{null}
			</ProFeatureGate>
		</div>
	);
};

const EmptyStateTables = () => {
	const navigate = useNavigate();

	return (
		<div className="w-full px-4 md:px-8">
			<div className="flex flex-col items-center justify-center text-center bg-gray-50 border-2 border-dashed border-gray-200 rounded-lg p-10">
				{/* Text Content Section */}
				<div className="mx-auto">
					<h3 className="text-lg font-bold text-gray-900 mb-2">
						{__('Welcome to ProductBay', 'productbay')}
					</h3>

					<p className="text-gray-500 mb-6 text-sm">
						{__(
							"You haven't created any tables yet. Create your first table to get started!",
							'productbay'
						)}
					</p>

					{/* Call to Action Button */}
					<Button
						type="button"
						onClick={() => navigate(NEW_TABLE_PATH.path)}
						className="cursor-pointer inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors shadow-sm"
					>
						<PlusIcon size={16} className="mr-2" />
						{NEW_TABLE_PATH.label}
					</Button>
				</div>
			</div>
		</div>
	);
};

export default Tables;
