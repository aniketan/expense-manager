import React from 'react';
import {
    BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer
} from 'recharts';

// Custom tooltip component to format currency
const CustomTooltip = ({ active, payload, label }) => {
    if (active && payload && payload.length) {
        return (
            <div className="bg-white p-2 border border-secondary rounded shadow-sm">
                <p className="mb-0 text-sm">{label}</p>
                {payload.map((entry, index) => (
                    <p key={index} className="mb-0 text-sm" style={{ color: entry.color }}>
                        {entry.name}: ₹{entry.value.toFixed(2)}
                    </p>
                ))}
            </div>
        );
    }
    return null;
};

/**
 * Monthly Trend Chart - Line chart showing income, expense, and balance over time
 */
export function MonthlyTrendChart({ data }) {
    if (!data || data.length === 0) {
        return (
            <div className="text-center text-muted py-5">
                <p>No data available</p>
            </div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={data} margin={{ top: 5, right: 30, left: 0, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip content={<CustomTooltip />} />
                <Legend />
                <Line
                    type="monotone"
                    dataKey="income"
                    stroke="#28a745"
                    strokeWidth={2}
                    dot={{ r: 4 }}
                    activeDot={{ r: 6 }}
                    name="Income"
                />
                <Line
                    type="monotone"
                    dataKey="expense"
                    stroke="#dc3545"
                    strokeWidth={2}
                    dot={{ r: 4 }}
                    activeDot={{ r: 6 }}
                    name="Expense"
                />
                <Line
                    type="monotone"
                    dataKey="balance"
                    stroke="#0d6efd"
                    strokeWidth={2}
                    dot={{ r: 4 }}
                    activeDot={{ r: 6 }}
                    name="Balance"
                />
            </LineChart>
        </ResponsiveContainer>
    );
}

/**
 * Expense vs Income Chart - Bar chart comparing income and expense
 */
export function ExpenseVsIncomeChart({ data }) {
    if (!data || data.length === 0) {
        return (
            <div className="text-center text-muted py-5">
                <p>No data available</p>
            </div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={300}>
            <BarChart data={data} margin={{ top: 5, right: 30, left: 0, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip content={<CustomTooltip />} />
                <Legend />
                <Bar dataKey="income" fill="#28a745" name="Income" radius={[8, 8, 0, 0]} />
                <Bar dataKey="expense" fill="#dc3545" name="Expense" radius={[8, 8, 0, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}

/**
 * Category Breakdown Chart - Pie chart showing expenses by category
 */
export function CategoryBreakdownChart({ data }) {
    const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8', '#82CA9D', '#FFC658', '#FF7C7C', '#7C8DFF', '#8DD1E1'];

    if (!data || data.length === 0) {
        return (
            <div className="text-center text-muted py-5">
                <p>No data available</p>
            </div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={300}>
            <PieChart>
                <Pie
                    data={data}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, value }) => `${name}: ₹${value.toFixed(0)}`}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                >
                    {data.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                </Pie>
                <Tooltip
                    formatter={(value) => `₹${value.toFixed(2)}`}
                    contentStyle={{ backgroundColor: '#fff', border: '1px solid #ccc', borderRadius: '4px' }}
                />
            </PieChart>
        </ResponsiveContainer>
    );
}

/**
 * Account Breakdown Chart - Shows balance distribution across accounts
 */
export function AccountBreakdownChart({ data }) {
    if (!data || data.length === 0) {
        return (
            <div className="text-center text-muted py-5">
                <p>No data available</p>
            </div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={300}>
            <BarChart data={data} margin={{ top: 5, right: 30, left: 0, bottom: 5 }} layout="vertical">
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis type="number" />
                <YAxis dataKey="name" type="category" width={100} />
                <Tooltip content={<CustomTooltip />} />
                <Bar dataKey="balance" fill="#0d6efd" name="Balance" radius={[0, 8, 8, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}

/**
 * Transaction Type Distribution Chart - Shows income vs expense ratio
 */
export function TransactionTypeDistributionChart({ data }) {
    const COLORS = ['#28a745', '#dc3545'];

    if (!data || data.length === 0) {
        return (
            <div className="text-center text-muted py-5">
                <p>No data available</p>
            </div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={300}>
            <PieChart>
                <Pie
                    data={data}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, value }) => `${name}: ₹${value.toFixed(0)}`}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                >
                    {data.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                </Pie>
                <Tooltip
                    formatter={(value) => `₹${value.toFixed(2)}`}
                    contentStyle={{ backgroundColor: '#fff', border: '1px solid #ccc', borderRadius: '4px' }}
                />
            </PieChart>
        </ResponsiveContainer>
    );
}
