import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';
import {
    ExpenseVsIncomeChart,
    CategoryBreakdownChart,
    MonthlyTrendChart,
    AccountBreakdownChart,
    TransactionTypeDistributionChart
} from '../../Components/Charts/FinancialCharts';

export default function Analytics({ chartData = {} }) {
    return (
        <BootstrapLayout>
            <Head title="Analytics & Reports" />

            {/* Header */}
            <div className="row mb-4">
                <div className="col-12">
                    <h1>
                        <i className="fas fa-chart-bar text-primary me-3"></i>
                        Analytics & Reports
                    </h1>
                    <p className="text-muted">Track your financial trends and insights</p>
                </div>
            </div>

            {/* Monthly Trend Chart */}
            <div className="row mb-4">
                <div className="col-12">
                    <div className="card">
                        <div className="card-header bg-light">
                            <h5 className="mb-0">
                                <i className="fas fa-chart-line me-2 text-info"></i>Monthly Trend (Last 12 Months)
                            </h5>
                        </div>
                        <div className="card-body">
                            {chartData.monthlyTrend ? (
                                <MonthlyTrendChart data={chartData.monthlyTrend} />
                            ) : (
                                <p className="text-muted">No data available</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Expense vs Income & Transaction Distribution */}
            <div className="row mb-4">
                <div className="col-lg-6 mb-4">
                    <div className="card">
                        <div className="card-header bg-light">
                            <h5 className="mb-0">
                                <i className="fas fa-chart-bar me-2 text-success"></i>Income vs Expense (Current vs Previous Month)
                            </h5>
                        </div>
                        <div className="card-body">
                            {chartData.monthlyComparison ? (
                                <ExpenseVsIncomeChart data={chartData.monthlyComparison} />
                            ) : (
                                <p className="text-muted">No data available</p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="col-lg-6 mb-4">
                    <div className="card">
                        <div className="card-header bg-light">
                            <h5 className="mb-0">
                                <i className="fas fa-pie-chart me-2 text-warning"></i>Income vs Expense (Current Month)
                            </h5>
                        </div>
                        <div className="card-body">
                            {chartData.transactionTypeDistribution ? (
                                <TransactionTypeDistributionChart data={chartData.transactionTypeDistribution} />
                            ) : (
                                <p className="text-muted">No data available</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Category Breakdown & Account Distribution */}
            <div className="row mb-4">
                <div className="col-lg-6 mb-4">
                    <div className="card">
                        <div className="card-header bg-light">
                            <h5 className="mb-0">
                                <i className="fas fa-pie-chart me-2 text-danger"></i>Top Expenses by Category (Current Month)
                            </h5>
                        </div>
                        <div className="card-body">
                            {chartData.categoryBreakdown ? (
                                <CategoryBreakdownChart data={chartData.categoryBreakdown} />
                            ) : (
                                <p className="text-muted">No data available</p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="col-lg-6 mb-4">
                    <div className="card">
                        <div className="card-header bg-light">
                            <h5 className="mb-0">
                                <i className="fas fa-wallet me-2 text-primary"></i>Account Balance Distribution
                            </h5>
                        </div>
                        <div className="card-body">
                            {chartData.accountBreakdown ? (
                                <AccountBreakdownChart data={chartData.accountBreakdown} />
                            ) : (
                                <p className="text-muted">No data available</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Summary Cards */}
            <div className="row">
                <div className="col-md-6 col-lg-3 mb-4">
                    <div className="card border-left-success">
                        <div className="card-body">
                            <h6 className="card-title text-muted mb-2">
                                <i className="fas fa-arrow-up text-success me-2"></i>Total Income
                            </h6>
                            <p className="card-text h5 text-success mb-0">
                                ₹{chartData.monthlyComparison?.[0]?.income?.toFixed(2) || '0.00'}
                            </p>
                            <small className="text-muted">Current Month</small>
                        </div>
                    </div>
                </div>

                <div className="col-md-6 col-lg-3 mb-4">
                    <div className="card border-left-danger">
                        <div className="card-body">
                            <h6 className="card-title text-muted mb-2">
                                <i className="fas fa-arrow-down text-danger me-2"></i>Total Expense
                            </h6>
                            <p className="card-text h5 text-danger mb-0">
                                ₹{chartData.monthlyComparison?.[0]?.expense?.toFixed(2) || '0.00'}
                            </p>
                            <small className="text-muted">Current Month</small>
                        </div>
                    </div>
                </div>

                <div className="col-md-6 col-lg-3 mb-4">
                    <div className="card border-left-info">
                        <div className="card-body">
                            <h6 className="card-title text-muted mb-2">
                                <i className="fas fa-balance-scale text-info me-2"></i>Net Balance
                            </h6>
                            <p className="card-text h5 text-info mb-0">
                                ₹{(chartData.monthlyComparison?.[0]?.income - chartData.monthlyComparison?.[0]?.expense)?.toFixed(2) || '0.00'}
                            </p>
                            <small className="text-muted">Current Month</small>
                        </div>
                    </div>
                </div>

                <div className="col-md-6 col-lg-3 mb-4">
                    <div className="card border-left-warning">
                        <div className="card-body">
                            <h6 className="card-title text-muted mb-2">
                                <i className="fas fa-percentage text-warning me-2"></i>Expense Ratio
                            </h6>
                            <p className="card-text h5 text-warning mb-0">
                                {chartData.monthlyComparison?.[0]?.income > 0
                                    ? ((chartData.monthlyComparison?.[0]?.expense / chartData.monthlyComparison?.[0]?.income) * 100).toFixed(1)
                                    : '0'}%
                            </p>
                            <small className="text-muted">Current Month</small>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}
