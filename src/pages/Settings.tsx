import { SaveIcon, RefreshCwIcon, RotateCcwIcon } from 'lucide-react';
import { useSettingsStore } from '@/store/settingsStore';
import { Tabs, TabOption } from '@/components/ui/Tabs';
import { Skeleton } from '@/components/ui/Skeleton';
import { useEffect, useState, useRef } from 'react';
import { useToast } from '@/context/ToastContext';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { useUrlTab } from '@/hooks/useUrlTab';
import { Slot } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React from 'react';
import {
	createDefaultSource,
	createDefaultStyle,
	createDefaultSettings,
	createDefaultColumns,
	DataSource,
	TableStyle,
	TableSettings as TableSettingsType,
} from '@/types';

// Static imports for settings components
import AdminBarOptions from '@/components/Settings/AdminBarOptions';
import UninstallOptions from '@/components/Settings/UninstallOptions';
import ClearDataOptions from '@/components/Settings/ClearDataOptions';
import DefaultSettings from '@/components/Settings/DefaultSettings';
import ProPromotion from '@/components/Settings/ProPromotion';
import LogViewer from '@/components/Settings/LogViewer';

const VALID_SETTINGS_TABS = ['default', 'plugin', 'log', 'license'] as const;
type SettingsTabValue = (typeof VALID_SETTINGS_TABS)[number];

const SETTINGS_TABS: TabOption<SettingsTabValue>[] = [
	{
		value: 'default',
		label: __('Default Configuration', 'productbay'),
	},
	{
		value: 'plugin',
		label: __('Plugin Settings', 'productbay'),
	},
	{
		value: 'log',
		label: __('Log', 'productbay'),
	},
];

/**
 * Settings Page Component
 *
 * Main configuration hub for global defaults and plugin-wide behavior.
 *
 * @since 1.0.0
 */
const Settings = () => {
	const [activeTab, setActiveTab] = useUrlTab<SettingsTabValue>('default', VALID_SETTINGS_TABS);
	const [showReloadModal, setShowReloadModal] = useState(false);
	const adminBarValueBeforeSave = useRef<boolean | undefined>(undefined);

	// -- State Management via custom store --
	const {
		settings, // Current settings object (including unsaved changes)
		loading, // Flag for initial data fetching
		saving, // Flag for active save request
		isDirty, // True if current settings differ from originalSettings
		originalSettings, // Pristine settings from DB
		fetchSettings, // Action to load settings from REST API
		updateSettings, // Action to update local state
		saveSettings, // Action to persist state to DB
	} = useSettingsStore();

	/**
	 * Toast notification hook for user feedback.
	 */
	const { toast } = useToast();

	/**
	 * Fetch settings on component mount.
	 */
	useEffect(() => {
		fetchSettings();
	}, [fetchSettings]);

	const activeTabs = React.useMemo(() => {
		const baseTabs: TabOption<SettingsTabValue>[] = [...SETTINGS_TABS];

		baseTabs.push({
			value: 'license',
			label: __('License', 'productbay'),
		});

		return baseTabs;
	}, []);

	/**
	 * Derived configuration objects from the global settings.
	 * Default fallback values are applied if the data is missing.
	 */
	const tableDefaults = settings.table_defaults || {};
	const source = tableDefaults.source || createDefaultSource();
	const style = tableDefaults.style || createDefaultStyle();
	const tableSettings = tableDefaults.settings || createDefaultSettings();
	const columns = tableDefaults.columns || createDefaultColumns();

	// -- Handlers for Panel Updates --

	/**
	 * Generic updater for global table defaults.
	 * Merges existing data with new changes for the specified key.
	 *
	 * @param key The part of table_defaults to update.
	 * @param data The new partial data.
	 */
	const updateDefaults = (key: 'source' | 'style' | 'settings' | 'columns', data: any) => {
		updateSettings({
			...settings,
			table_defaults: {
				...tableDefaults,
				[key]: key === 'columns' ? data : { ...tableDefaults[key], ...data },
			},
		});
	};

	// Specialized source type handler
	const setSourceType = (type: any) => updateDefaults('source', { ...source, type });

	// Specialized columns handler
	const setColumns = (cols: any) => updateDefaults('columns', cols);

	// Styling handlers for granular sub-objects
	const setHeaderStyle = (v: any) =>
		updateDefaults('style', {
			...style,
			header: { ...style.header, ...v },
		});
	const setBodyStyle = (v: any) =>
		updateDefaults('style', { ...style, body: { ...style.body, ...v } });
	const setButtonStyle = (v: any) =>
		updateDefaults('style', {
			...style,
			button: { ...style.button, ...v },
		});
	const setLayoutStyle = (v: any) =>
		updateDefaults('style', {
			...style,
			layout: { ...style.layout, ...v },
		});
	const setTypographyStyle = (v: any) =>
		updateDefaults('style', {
			...style,
			typography: { ...style.typography, ...v },
		});
	const setHoverStyle = (v: any) =>
		updateDefaults('style', {
			...style,
			hover: { ...style.hover, ...v },
		});

	// Functionality handlers for granular sub-objects
	const setFeatures = (v: any) =>
		updateDefaults('settings', {
			...tableSettings,
			features: { ...tableSettings.features, ...v },
		});
	const setPagination = (v: any) =>
		updateDefaults('settings', {
			...tableSettings,
			pagination: { ...tableSettings.pagination, ...v },
		});
	const setCart = (v: any) =>
		updateDefaults('settings', {
			...tableSettings,
			cart: { ...tableSettings.cart, ...v },
		});
	const setFilters = (v: any) =>
		updateDefaults('settings', {
			...tableSettings,
			filters: { ...tableSettings.filters, ...v },
		});

	/**
	 * Persists current settings state to the backend.
	 * Handles success/error notifications and triggers reload modal if needed.
	 */
	const handleSave = async () => {
		adminBarValueBeforeSave.current = originalSettings.show_admin_bar;

		try {
			await saveSettings();
			toast({
				title: __('Settings saved!', 'productbay'),
				description: __('Your settings have been saved successfully.', 'productbay'),
				type: 'success',
			});

			if (adminBarValueBeforeSave.current !== settings.show_admin_bar) {
				setShowReloadModal(true);
			}
		} catch (error) {
			toast({
				title: __('Failed to save settings', 'productbay'),
				description:
					(error as Error).message ||
					__('An error occurred while saving your settings.', 'productbay'),
				type: 'error',
			});
		}
	};

	const handleReload = () => {
		window.location.reload();
	};

	const [showResetModal, setShowResetModal] = useState(false);

	const handleResetDefaults = () => {
		setShowResetModal(true);
	};

	const confirmResetDefaults = () => {
		updateSettings({
			...settings,
			table_defaults: {
				source: createDefaultSource(),
				style: createDefaultStyle(),
				settings: createDefaultSettings(),
				columns: createDefaultColumns(),
			},
		});
		setShowResetModal(false);
		toast({
			title: __('Defaults Reset', 'productbay'),
			description: __(
				'Global default settings have been reset to factory values.',
				'productbay'
			),
			type: 'success',
		});
	};

	const SettingsTabSkeleton = () => (
		<div className="p-6 space-y-6">
			<div className="h-7 w-48 bg-gray-200 rounded animate-pulse mb-6" />
			<div className="space-y-4">
				<Skeleton className="h-10 w-full" />
				<Skeleton className="h-10 w-full" />
			</div>
		</div>
	);

	return (
		<div className="w-full space-y-6 relative">
			{/* 
				Main Settings Header - Sticky with Backdrop Blur
				Contains the page title and global actions (Reset/Save).
				The border and background become visible as users scroll.
			*/}
			<div className="sticky top-0 z-40 flex items-center justify-between pt-[48px] pb-4 mb-2 bg-wp-bg border-b border-gray-200/50">
				<h1 className="text-2xl font-bold text-gray-800 m-0">
					{__('Settings', 'productbay')}
				</h1>
				{/* Hide save/reset buttons on the Log tab (read-only) */}
				{activeTab !== 'log' && (
					<div className="flex items-center gap-3">
						{/* Reset button only visible on the Default Configuration tab */}
						{activeTab === 'default' && (
							<Button
								variant="outline"
								onClick={handleResetDefaults}
								className="text-gray-500 hover:bg-red-500 hover:text-white cursor-pointer"
								title={__('Reset to Factory Defaults', 'productbay')}
							>
								{__('Reset Defaults', 'productbay')}
								<RotateCcwIcon className="w-4 h-4 ml-2" />
							</Button>
						)}
						{/* Save button - dynamically disabled based on store state */}
						<Button
							onClick={handleSave}
							disabled={saving || !isDirty}
							variant="default"
							className={`w-36 ${saving || !isDirty ? 'cursor-not-allowed' : 'cursor-pointer'
								}`}
						>
							{saving ? __('Saving...', 'productbay') : __('Save Changes', 'productbay')}
							<SaveIcon className="w-4 h-4 ml-2" />
						</Button>
					</div>
				)}
			</div>

			{/* 
				Tabbed Navigation 
				Controls which configuration panel is currently visible.
				State is managed via 'activeTab' connected to the URL query string.
			*/}
			<Tabs
				tabs={activeTabs}
				value={activeTab}
				onChange={setActiveTab}
				aria-label={__('Settings tabs', 'productbay')}
			>
				{/**
				 * Tab 1: Default Configuration
				 * 
				 * Allows setting global defaults for new tables (Source, Style, Functionality).
				 */}
				{activeTab === 'default' && (
					<DefaultSettings
						source={source}
						setSourceType={setSourceType}
						columns={columns}
						setColumns={setColumns}
						tableSettings={tableSettings}
						setFeatures={setFeatures}
						style={style}
						setHeaderStyle={setHeaderStyle}
						setBodyStyle={setBodyStyle}
						setButtonStyle={setButtonStyle}
						setLayoutStyle={setLayoutStyle}
						setTypographyStyle={setTypographyStyle}
						setHoverStyle={setHoverStyle}
						setPagination={setPagination}
						setCart={setCart}
						setFilters={setFilters}
					/>
				)}

				{/**
				 * Tab 3: Plugin Configuration Tab 
				 */}
				{activeTab === 'plugin' && (
					<div className="space-y-6">
						<Slot name="productbay-pro-settings-plugin" />
						<AdminBarOptions
							settings={settings}
							setSettings={updateSettings}
							loading={loading}
						/>
						<UninstallOptions
							settings={settings}
							setSettings={updateSettings}
							loading={loading}
						/>
						<ClearDataOptions loading={loading} />
					</div>
				)}

				{/**
				 * Tab 3: Activity Log
				 */}
				{activeTab === 'log' && (
					<div className="p-4">
						<LogViewer />
					</div>
				)}

				{/**
				 * Tab 4: License Tab 
				 */}
				{activeTab === 'license' && (
					<div className="space-y-6">
						{productBaySettings.proActive ? (
							<Slot name="productbay-pro-settings-license" />
						) : (
							<ProPromotion />
						)}
					</div>
				)}
			</Tabs>

			{/* Reload Modal */}
			<Modal
				isOpen={showReloadModal}
				onClose={() => setShowReloadModal(false)}
				title={__('Page Reload Required', 'productbay')}
				maxWidth="sm"
				closeOnBackdropClick={false}
				primaryButton={{
					text: __('Reload Now', 'productbay'),
					onClick: handleReload,
					variant: 'primary',
					icon: <RefreshCwIcon className="w-4 h-4" />,
				}}
				secondaryButton={{
					text: __('Later', 'productbay'),
					onClick: () => setShowReloadModal(false),
					variant: 'secondary',
				}}
			>
				<p className="text-gray-600 m-0">
					{__(
						'The admin bar setting requires a page reload to take effect. Would you like to reload now?',
						'productbay'
					)}
				</p>
			</Modal>

			{/* Reset Default Configuration Modal */}
			<Modal
				isOpen={showResetModal}
				onClose={() => setShowResetModal(false)}
				title={__('Reset Global Defaults?', 'productbay')}
				maxWidth="sm"
				closeOnBackdropClick={true}
				primaryButton={{
					text: __('Yes, Reset Defaults', 'productbay'),
					onClick: confirmResetDefaults,
					variant: 'danger',
					icon: <RotateCcwIcon className="w-4 h-4" />,
				}}
				secondaryButton={{
					text: __('Cancel', 'productbay'),
					onClick: () => setShowResetModal(false),
					variant: 'secondary',
				}}
			>
				<p className="text-gray-600 m-0">
					{__(
						'Are you sure you want to reset all global default configurations to their factory settings? This action cannot be undone.',
						'productbay'
					)}
				</p>
			</Modal>
		</div>
	);
};
export default Settings;
