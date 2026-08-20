import {
	TablePropertiesIcon,
	MonitorIcon,
	SettingsIcon,
	SaveIcon,
	CopyIcon,
	InfoIcon,
	TrashIcon,
	AlertCircleIcon,
	PlusIcon,
	LoaderIcon,
	CopyCheckIcon,
	DownloadIcon,
} from 'lucide-react';
import { useImportExportStore } from '@/store/importExportStore';
import { ProFeatureGate } from '@/components/ui/ProFeatureGate';
import { useCopyToClipboard } from '@/hooks/useCopyToClipboard';
import { EditableText } from '@/components/ui/EditableText';
import ProductBayIcon from '@/components/ui/ProductBayIcon';
import { useParams, useNavigate } from 'react-router-dom';
import LivePreview from '@/components/Table/LivePreview';
import { PATHS, NEW_TABLE_PATH } from '@/utils/routes';
import { getTableStatusBadge } from '@/utils/tableStatus';
import TabOptions from '@/components/Table/TabOptions';
import TabDisplay from '@/components/Table/TabDisplay';
import { Tabs, TabOption } from '@/components/ui/Tabs';
import { useTableStore } from '@/store/tableStore';
import TabTable from '@/components/Table/TabTable';
import { Tooltip } from '@/components/ui/Tooltip';
import { useToast } from '@/context/ToastContext';
import { Toggle } from '@/components/ui/Toggle';
import { Button } from '@/components/ui/Button';
import { useUrlTab } from '@/hooks/useUrlTab';
import { Modal } from '@/components/ui/Modal';
import { useState, useEffect } from 'react';
import { apiFetch } from '@/utils/api';
import { __ } from '@wordpress/i18n';
import { cn } from '@/utils/cn';

/* =============================================================================
 * Table Page
 * =============================================================================
 * Main page for configuring an individual table with three sections:
 * - Table: Configure table data and structure
 * - Display: Configure how the table is displayed
 * - Options: Additional table options
 *
 * Supports URL - based tab navigation via search params.
 * Example: # / new? tab = options activates Options tab.
 *
 * @since 1.0.0
 * ============================================================================= */

/**
 * Define the available tab values as a union type for type safety.
 * These correspond to the different configuration screens of a table.
 */
type TableTabValue = 'table' | 'display' | 'options';

/**
 * Valid tab values for URL search param validation.
 * Used by the useUrlTab hook to validate the ?tab= parameter in the URL.
 */
const VALID_TABLE_TABS = ['table', 'display', 'options'] as const;

/**
 * Configuration for the table editor tabs.
 * Each option includes a value, a localized label, and a Lucide icon.
 */
const TABLE_TABS: TabOption<TableTabValue>[] = [
	{
		value: 'table',
		label: __('Table', 'productbay'),
		icon: <TablePropertiesIcon />,
	},
	{
		value: 'display',
		label: __('Display', 'productbay'),
		icon: <MonitorIcon />,
	},
	{
		value: 'options',
		label: __('Options', 'productbay'),
		icon: <SettingsIcon />,
	},
];

/**
 * Table Page Component
 *
 * This is the primary editor interface for individual tables. It provides:
 * - Tabbed navigation between different configuration sections.
 * - Live preview of the table as it's being configured.
 * - Controls for saving, deleting, and exporting table configurations.
 * - Display of shortcode and permalink for easy sharing and integration.
 *
 * @returns {JSX.Element} The rendered Table editor page.
 */
const Table = () => {
	const { id } = useParams<{ id: string }>();
	const isNewTable = !id || id === 'new';
	const navigate = useNavigate();
	const { isCopied, copy: copyToClipboard } = useCopyToClipboard();
	const { isCopied: isPermalinkCopied, copy: copyPermalink } = useCopyToClipboard();

	// Store access
	const {
		tableId,
		tableTitle,
		setTitle,
		tableStatus,
		setStatus,
		loadTable,
		saveTable,
		isLoading,
		error,
		source,
		columns,
		settings,
		style,
		permalink,
	} = useTableStore();

	const { openExportModal } = useImportExportStore();

	// Load data on mount or ID change
	useEffect(() => {
		if (!isNewTable) {
			loadTable(parseInt(id));
		} else {
			// Reset store for new table
			useTableStore.getState().resetStore();
		}
	}, [id, isNewTable, loadTable]);

	// Toast notification
	const { toast } = useToast();

	// Validation state
	const [titleError, setTitleError] = useState<string | undefined>(undefined);
	const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

	const shortcode = `[productbay id="${tableId}"]`;

	/**
	 * Opens the deletion confirmation modal.
	 */
	const handleDelete = () => {
		setIsDeleteModalOpen(true);
	};

	/**
	 * Performs the actual table deletion after user confirmation.
	 * Sends a DELETE request to the API, shows a toast notification,
	 * and redirects back to the table listing page.
	 *
	 * @async
	 */
	const confirmDelete = async () => {
		setIsDeleteModalOpen(false);
		try {
			await apiFetch(`tables/${tableId}`, { method: 'DELETE' });
			toast({
				title: __('Deleted', 'productbay'),
				description: __('Table deleted successfully.', 'productbay'),
				type: 'success',
			});
			// Redirect to tables list (Soft navigation)
			navigate(PATHS.TABLES);
		} catch (error) {
			toast({
				title: __('Error', 'productbay'),
				description: __('Failed to delete table.', 'productbay'),
				type: 'error',
			});
		}
	};

	const [isSaving, setIsSaving] = useState(false);

	/**
	 * Validates and saves the current table configuration.
	 * If the table is new, it redirects to the edit URL after a successful save.
	 *
	 * @async
	 */
	const handleSave = async () => {
		// Validation: Table Name is required
		if (!tableTitle.trim()) {
			const errorMsg = __('Table name is required.', 'productbay');
			setTitleError(errorMsg);
			toast({
				title: __('Validation Error', 'productbay'),
				description: errorMsg,
				type: 'error',
			});
			return;
		}

		setIsSaving(true);
		const success = await saveTable();
		setIsSaving(false);

		if (success) {
			toast({
				title: __('Success', 'productbay'),
				description: __('Table saved successfully.', 'productbay'),
				type: 'success',
			});

			// If it was a new table, redirect to the edit URL with the new ID
			const newId = useTableStore.getState().tableId;
			if (isNewTable && newId) {
				// Soft redirect to the edit route
				navigate(PATHS.TABLE_EDITOR.replace(':id', newId.toString()));
			}
		} else {
			toast({
				title: __('Error', 'productbay'),
				description: error || __('Failed to save table.', 'productbay'),
				type: 'error',
			});
		}
	};

	/**
	 * Updates the table title in the store and clears any title validation errors.
	 *
	 * @param {string} newName The new name for the table.
	 */
	const handleNameChange = (newName: string) => {
		setTitle(newName);
		if (titleError) setTitleError(undefined);
	};

	/**
	 * Sync tab state with URL search params.
	 * - Reading: #/new?tab=options → activeTab = 'options'
	 * - Writing: setActiveTab('display') → URL becomes #/new?tab=display
	 */
	const [activeTab, setActiveTab] = useUrlTab<TableTabValue>('table', VALID_TABLE_TABS);

	// Derived state for UI
	const isActive = tableStatus === 'publish';

	if (isLoading && !isNewTable) {
		return (
			<div className="flex items-center justify-center min-h-[400px]">
				<ProductBayIcon className="animate-pulse size-12" />
			</div>
		);
	}

	// Error / Not Found State
	if (error) {
		return (
			<div className="flex flex-col items-center justify-center min-h-[400px] text-center p-8 bg-white rounded-lg border border-gray-200">
				<div className="bg-red-50 p-4 rounded-full mb-4 flex items-center justify-center">
					<AlertCircleIcon className="size-8 text-red-500" />
				</div>
				<h2 className="text-xl font-bold text-gray-800 mb-2">
					{__('Table Not Found', 'productbay')}
				</h2>
				<p className="text-gray-500 max-w-md mb-6">
					{__(
						'The table you are looking for does not exist or has been deleted.',
						'productbay'
					)}
				</p>
				<div className="flex gap-4">
					<Button
						variant="outline"
						className="cursor-pointer"
						onClick={() => navigate(PATHS.TABLES)}
					>
						{__('View Tables', 'productbay')}
					</Button>
					<Button
						onClick={() => {
							// Reset store and go to new table
							useTableStore.getState().resetStore();
							navigate(NEW_TABLE_PATH.path);
						}}
						className="cursor-pointer"
					>
						<PlusIcon className="size-4 mr-2" />
						{NEW_TABLE_PATH.label}
					</Button>
				</div>
			</div>
		);
	}

	return (
		<>
			{/** Delete Table Confirmation Modal **/}
			<Modal
				isOpen={isDeleteModalOpen}
				onClose={() => setIsDeleteModalOpen(false)}
				title={__('Delete Table', 'productbay')}
				primaryButton={{
					text: __('Delete', 'productbay'),
					variant: 'danger',
					onClick: confirmDelete,
				}}
				secondaryButton={{
					text: __('Cancel', 'productbay'),
					variant: 'secondary',
					onClick: () => setIsDeleteModalOpen(false),
				}}
			>
				<p>
					{__(
						'Are you sure you want to delete this table? This action cannot be undone.',
						'productbay'
					)}
				</p>
			</Modal>

			{/** Table Header Section **/}
			<div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 pt-4 md:pt-0">
				{/* Conditional: Shortcode for already saved table */}
				{tableId && (
					<div className="bg-white border border-gray-200 rounded-lg p-4 flex flex-col gap-3 flex-1 transition-shadow">
						<div className="flex items-center justify-between gap-2">
							<span className="font-semibold text-sm text-gray-700">
								{__('Shortcode', 'productbay')}
							</span>
							<Tooltip
								content={__(
									'Copy this shortcode and paste it into any Page or Post to display this table.',
									'productbay'
								)}
							>
								<InfoIcon className="size-4 text-gray-400 cursor-pointer hover:text-gray-600 transition-colors" />
							</Tooltip>
						</div>
						<div className="flex flex-row items-center gap-2 mt-auto">
							<code className="flex-1 bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-sm text-gray-800 font-mono select-all truncate flex items-center">
								{shortcode}
							</code>
							<Button
								size="sm"
								variant="outline"
								onClick={() => copyToClipboard(shortcode)}
								className={`cursor-pointer transition-colors w-24 h-[38px] flex-shrink-0 ${isCopied
									? 'bg-green-50 hover:bg-green-100 text-green-700 border-green-200 hover:border-green-300'
									: 'bg-white hover:bg-gray-50 text-gray-700 border-gray-200'
									}`}
							>
								{isCopied ? (
									<>
										<CopyCheckIcon className="size-3.5 mr-1.5 shrink-0" />
										{__('Copied!', 'productbay')}
									</>
								) : (
									<>
										<CopyIcon className="size-3.5 mr-1.5 shrink-0" />
										{__('Copy', 'productbay')}
									</>
								)}
							</Button>
						</div>
					</div>
				)}

				{/* Conditional: Permalink for already saved table */}
				{(tableId && permalink) && (
					<div className="bg-white border border-gray-200 rounded-lg p-4 flex flex-col gap-3 flex-1 transition-shadow">
						<div className="flex items-center justify-between gap-2">
							<span className="font-semibold text-sm text-gray-700">
								{__('Permalink', 'productbay')}
							</span>
							<Tooltip
								content={__(
									'This is the direct, full-page link for this table.',
									'productbay'
								)}
							>
								<InfoIcon className="size-4 text-gray-400 cursor-pointer hover:text-gray-600 transition-colors" />
							</Tooltip>
						</div>
						<div className="flex flex-row items-center gap-2 mt-auto">
							<div className="flex-1 bg-gray-50 border border-gray-200 rounded-md px-3 py-2 min-w-0 flex items-center">
								<a
									href={permalink}
									target="_blank"
									rel="noreferrer"
									className="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline truncate block w-full"
									title={permalink}
								>
									{permalink}
								</a>
							</div>
							<Button
								size="sm"
								variant="outline"
								onClick={() => copyPermalink(permalink)}
								className={`cursor-pointer transition-colors w-24 h-[38px] flex-shrink-0 ${isPermalinkCopied
									? 'bg-green-50 hover:bg-green-100 text-green-700 border-green-200 hover:border-green-300'
									: 'bg-white hover:bg-gray-50 text-gray-700 border-gray-200'
									}`}
							>
								{isPermalinkCopied ? (
									<>
										<CopyCheckIcon className="size-3.5 mr-1.5 shrink-0" />
										{__('Copied!', 'productbay')}
									</>
								) : (
									<>
										<CopyIcon className="size-3.5 mr-1.5 shrink-0" />
										{__('Copy', 'productbay')}
									</>
								)}
							</Button>
						</div>
					</div>
				)}
			</div>

			{/* Table Actions Header */}
			<div className="sticky top-0 md:top-[32px] z-20 bg-wp-bg/95 backdrop-blur-sm -mx-4 px-4 py-3 mb-4 border-b border-gray-200/50 sm:-mx-6 sm:px-6 md:-mx-8 md:px-8">
				<div className="flex flex-col md:flex-row items-center justify-between gap-4">
					{/* Table Name */}
					<div className="order-2 md:order-1">
						<EditableText
							value={tableTitle}
							onChange={handleNameChange}
							error={titleError}
							placeholder={__('Enter table name...', 'productbay')}
						/>
					</div>
					{/* Controls */}
					<div className="flex items-center justify-between md:justify-left gap-4 order-1 md:order-2">
						{/* Export Button (Only for existing tables) */}
						{!isNewTable && (
							<ProFeatureGate featureName={__('Export Table', 'productbay')}>
								<Tooltip content={__('Export this table configuration', 'productbay')}>
									<Button
										size="sm"
										variant="ghost"
										onClick={() =>
											openExportModal(
												[
													{
														id: tableId!,
														title: tableTitle,
														status: tableStatus,
														source,
														columns,
														settings,
														style,
													},
												],
												[tableId!]
											)
										}
										className="text-blue-500 hover:text-white bg-white hover:bg-blue-600 px-2 cursor-pointer"
									>
										<DownloadIcon className="size-4" />
									</Button>
								</Tooltip>
							</ProFeatureGate>
						)}

						{/* Delete Button (Only for existing tables) */}
						{!isNewTable && (
							<Tooltip content={__('Delete this table', 'productbay')}>
								<Button
									size="sm"
									variant="ghost"
									onClick={handleDelete}
									className="bg-white text-red-500 hover:text-white hover:bg-red-600 px-2 cursor-pointer"
								>
									<TrashIcon className="size-4" />
								</Button>
							</Tooltip>
						)}

						{/* Published/Private toggle with status indicator - hover feedback on container */}
						<div className="flex items-center justify-between w-40 gap-2 bg-white px-4 py-2 rounded-md transition-colors">
							<div className="flex items-center gap-2">
								{/* Status dot indicator */}
								<span
									className={cn(
										'size-2 rounded-full',
										isActive ? 'bg-green-500' : 'bg-gray-400'
									)}
								/>
								{/* Status label with dynamic color - fixed width to prevent layout shift */}
								<span
									className={cn(
										'text-sm font-medium min-w-[52px]',
										getTableStatusBadge(tableStatus).textClassName
									)}
								>
									{getTableStatusBadge(tableStatus).label}
								</span>
							</div>
							{/* Toggle switch - only way to toggle */}
							<Toggle
								size="sm"
								checked={isActive}
								onChange={(e) =>
									setStatus(e.target.checked ? 'publish' : 'private')
								}
								title={
									isActive
										? __('Click toggle to set private', 'productbay')
										: __('Click toggle to publish', 'productbay')
								}
							/>
						</div>

						{/* Save Table button */}
						<Button
							size="default"
							onClick={handleSave}
							disabled={isSaving || isLoading}
							className={`w-32 flex items-center justify-between cursor-pointer ${isSaving ? 'opacity-75 cursor-wait' : ''
								}`}
						>
							{isSaving
								? __('Saving...', 'productbay')
								: __('Save Table', 'productbay')}
							{isSaving ? (
								<LoaderIcon className="size-4 ml-2 animate-spin" />
							) : (
								<SaveIcon className="size-4 ml-2" />
							)}
						</Button>
					</div>
				</div>
			</div>
			{/* Table configuration tabs */}
			<div className="w-full grid grid-cols-1 md:grid-cols-5 gap-6 rounded-lg">
				{/* Table configuration tabs */}
				<Tabs
					tabs={TABLE_TABS}
					value={activeTab}
					className="md:col-span-3"
					onChange={setActiveTab}
					aria-label={__('Table configuration tabs', 'productbay')}
				>
					{/* Render content based on active tab */}
					{activeTab === 'table' && <TabTable />}
					{activeTab === 'display' && <TabDisplay />}
					{activeTab === 'options' && <TabOptions />}
				</Tabs>

				{/* Live preview section */}
				<div className="md:col-span-2">
					<LivePreview className="sticky top-[132px] z-10" />
				</div>
			</div>
		</>
	);
};

export default Table;
