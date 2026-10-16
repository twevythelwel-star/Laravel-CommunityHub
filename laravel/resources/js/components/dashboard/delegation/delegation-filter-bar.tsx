import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Layers, Search } from 'lucide-react';
import type { CATEGORY_TABS } from './delegation-types';

export type DelegationViewMode = 'sections' | 'cards' | 'table';

type DelegationFilterBarProps = {
  tabs: typeof CATEGORY_TABS;
  tabCount: (tabKey: string) => number;
  selectedCategory: string;
  onSelectCategory: (tabKey: string) => void;
  searchQuery: string;
  onSearchQueryChange: (query: string) => void;
  statusFilter: string;
  onStatusFilterChange: (status: string) => void;
  viewMode: DelegationViewMode;
  onViewModeChange: (mode: DelegationViewMode) => void;
  isRenter: boolean;
};

/** Category tabs, then search, status filter and the sections / cards / table switch. */
export function DelegationFilterBar({
  tabs,
  tabCount,
  selectedCategory,
  onSelectCategory,
  searchQuery,
  onSearchQueryChange,
  statusFilter,
  onStatusFilterChange,
  viewMode,
  onViewModeChange,
  isRenter,
}: DelegationFilterBarProps) {
  return (
    <>
      {/* Category Navigation Bar */}
      <div className="overflow-x-auto pb-2 -mx-4 px-4 sm:mx-0 sm:px-0">
        <div className="flex items-center gap-1.5 min-w-max p-1 bg-muted/30 rounded-xl border border-border/40">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            const count = tabCount(tab.key);
            const isSelected = selectedCategory === tab.key;

            return (
              <button
                key={tab.key}
                onClick={() => onSelectCategory(tab.key)}
                className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
                  isSelected
                    ? 'bg-background text-foreground shadow-sm font-semibold border border-border/80'
                    : 'text-muted-foreground hover:text-foreground hover:bg-background/40'
                }`}
              >
                <Icon className={`w-3.5 h-3.5 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`} />
                <span>{tab.label}</span>
                <span
                  className={`ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
                    isSelected
                      ? 'bg-primary/15 text-primary'
                      : 'bg-muted text-muted-foreground'
                  }`}
                >
                  {count}
                </span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Filter & View Bar */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-card/60 p-3 rounded-xl border border-border/40">
        <div className="relative flex-1 min-w-[240px]">
          <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            type="text"
            placeholder="Search by name, pass ID (GP-...), email, or role..."
            value={searchQuery}
            onChange={(e) => onSearchQueryChange(e.target.value)}
            className="pl-9 h-9 text-xs bg-background/60"
          />
        </div>

        <div className="flex items-center gap-2">
          <Select value={statusFilter} onValueChange={onStatusFilterChange}>
            <SelectTrigger className="w-[140px] h-9 text-xs bg-background/60">
              <SelectValue placeholder="All Statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Statuses</SelectItem>
              <SelectItem value="active">Active Only</SelectItem>
              <SelectItem value="pending">Pending Approval</SelectItem>
              <SelectItem value="revoked_expired">Expired / Revoked</SelectItem>
            </SelectContent>
          </Select>

          <div className="flex items-center rounded-lg border border-border/40 p-0.5 bg-muted/20">
            {selectedCategory === 'all' && (
              <Button
                variant={viewMode === 'sections' ? 'secondary' : 'ghost'}
                size="sm"
                onClick={() => onViewModeChange('sections')}
                className="h-8 px-2.5 text-xs gap-1.5"
              >
                <Layers className="w-3.5 h-3.5 text-primary" />
                <span>{isRenter ? '5 Sections' : '7 Sections'}</span>
              </Button>
            )}
            <Button
              variant={viewMode === 'cards' ? 'secondary' : 'ghost'}
              size="sm"
              onClick={() => onViewModeChange('cards')}
              className="h-8 px-2.5 text-xs"
            >
              Cards
            </Button>
            <Button
              variant={viewMode === 'table' ? 'secondary' : 'ghost'}
              size="sm"
              onClick={() => onViewModeChange('table')}
              className="h-8 px-2.5 text-xs"
            >
              Table
            </Button>
          </div>
        </div>
      </div>
    </>
  );
}
