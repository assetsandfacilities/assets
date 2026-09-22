<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAPEX/OPEX Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f3f2f2;
            color: #201e1d;
        }
        .header {
            background: #201e1d;
            color: white;
            padding: 20px 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .container {
            padding: 30px;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius:0;
            box-shadow:none;
        }
        .card h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #444141;
        }
        .card p {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .section {
            background: white;
            padding: 20px;
            border-radius:0;
            box-shadow:none;
            margin-bottom: 25px;
        }
        .section h2 {
            margin-top: 0;
            font-size: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th, table td {
            padding: 12px;
            border-bottom: 1px solid #d7d3d3;
            text-align: left;
            font-size: 14px;
        }
        table th {
            background: #f3f2f2;
        }
        .badge-capex {
            background: #eae9e9;
            color: #ae1800;
            padding: 4px 10px;
            border-radius:0;
            font-size: 12px;
        }
        .badge-opex {
            background: #eae9e9;
            color: #201e1d;
            padding: 4px 10px;
            border-radius:0;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>QR-Based Asset and Inventory Requisition Management System</h1>
        <p>CAPEX and OPEX Dashboard</p>
    </div>

    <div class="container">
        <div class="cards">
            <div class="card">
                <h3>Total Items</h3>
                <p>{{ count($items) }}</p>
            </div>
            <div class="card">
                <h3>Total Departments</h3>
                <p>{{ count($departments) }}</p>
            </div>
            <div class="card">
                <h3>Total Suppliers</h3>
                <p>{{ count($suppliers) }}</p>
            </div>
            <div class="card">
                <h3>Total Acquisitions</h3>
                <p>{{ count($acquisitions) }}</p>
            </div>
        </div>

        <div class="section">
            <h2>Items Overview</h2>
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item['item_code'] ?? '' }}</td>
                            <td>{{ $item['name'] ?? '' }}</td>
                            <td>
                                @if(($item['item_type'] ?? '') === 'CAPEX')
                                    <span class="badge-capex">CAPEX</span>
                                @else
                                    <span class="badge-opex">OPEX</span>
                                @endif
                            </td>
                            <td>{{ $item['quantity'] ?? 0 }}</td>
                            <td>{{ $item['unit'] ?? '' }}</td>
                            <td>{{ $item['category']['name'] ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Departments</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Department Name</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departments as $department)
                        <tr>
                            <td>{{ $department['id'] ?? '' }}</td>
                            <td>{{ $department['name'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">No departments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>