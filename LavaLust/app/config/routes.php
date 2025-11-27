<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/

$router->get('/', 'Welcome::index');

// API: Users
// API endpoint
$router->post('/api/users', 'Users::create');

// Registration form (server-rendered)
$router->get('/register', 'Users::register');
$router->post('/register', 'Users::create');

// Login routes (supports form and API). Use match() for GET|POST on /login as requested.
$router->match('/login', 'Users::login', ['GET', 'POST']);
$router->match('/api/login', 'Users::login', ['POST']);
// Google OAuth token-based login (frontend posts id_token)
$router->post('/api/login/google', 'Users::google');

// Logout and me endpoints
$router->post('/api/logout', 'Users::logout');
$router->get('/api/me', 'Users::me');

// Departments API
$router->get('/api/departments', 'Departments::index');
$router->post('/api/departments', 'Departments::create');
// simple delete endpoint (expects id in body or query string)
$router->post('/api/departments/delete', 'Departments::delete');

// Workspaces API (for geolocation-based attendance checks)
$router->get('/api/workspaces', 'Workspaces::index');
$router->post('/api/workspaces', 'Workspaces::create');
$router->post('/api/workspaces/update', 'Workspaces::update');
$router->delete('/api/workspaces/{id}', 'Workspaces::delete');

// Employees API
$router->get('/api/employees', 'Employees::index');
$router->post('/api/employees', 'Employees::create');
$router->post('/api/employees/update', 'Employees::update');
$router->post('/api/employees/delete', 'Employees::delete');

// Rooms API
$router->get('/api/rooms', 'Rooms::index');
$router->post('/api/rooms', 'Rooms::create');
$router->post('/api/rooms/update', 'Rooms::update');
$router->post('/api/rooms/deactivate', 'Rooms::deactivate');
$router->post('/api/rooms/toggle-status', 'Rooms::toggle_status');

// Subjects API
$router->get('/api/subjects', 'Subjects::index');
$router->post('/api/subjects', 'Subjects::create');
$router->post('/api/subjects/update', 'Subjects::update');
$router->post('/api/subjects/deactivate', 'Subjects::deactivate');
$router->post('/api/subjects/toggle-status', 'Subjects::toggle_status');

// Class Sections API
$router->get('/api/sections', 'Sections::index');
$router->post('/api/sections', 'Sections::create');
$router->post('/api/sections/update', 'Sections::update');
$router->post('/api/sections/toggle-status', 'Sections::toggle_status');

// Positions API
$router->get('/api/positions', 'Positions::index');
$router->post('/api/positions', 'Positions::create');
$router->post('/api/positions/update', 'Positions::update');
$router->post('/api/positions/delete', 'Positions::delete');

// Schedules API
$router->get('/api/schedules', 'Schedules::index');
$router->get('/api/schedules/excel', 'Schedules::excel');
$router->get('/api/schedules/pdf', 'Schedules::pdf');
$router->post('/api/schedules', 'Schedules::create');
$router->post('/api/schedules/update', 'Schedules::update');
$router->post('/api/schedules/delete', 'Schedules::delete');

// Attendance API
$router->get('/api/attendance', 'Attendance::index');
$router->get('/api/attendance/employee', 'Attendance::employee');
$router->post('/api/attendance/clockin', 'Attendance::clockin');
$router->post('/api/attendance/clockout', 'Attendance::clockout');

// Faculty Schedule & Teaching Load API
$router->get('/api/faculty/:id/schedule', 'FacultySchedule::schedule');
$router->get('/api/faculty/:id/load', 'FacultySchedule::load');
$router->get('/api/faculty/:id/today', 'FacultySchedule::today');
$router->get('/api/faculty/:id/payroll', 'FacultySchedule::payroll');
$router->get('/api/faculty/:id/attendance-summary', 'FacultySchedule::attendance_summary');

// Faculty Attendance (Clock in/out for specific class sessions)
$router->post('/api/faculty/attendance/clockin', 'FacultySchedule::clockin');
$router->post('/api/faculty/attendance/clockout', 'FacultySchedule::clockout');

// Payroll API
$router->get('/api/payroll', 'Payroll::index');
$router->get('/api/payroll/period/{id}', 'Payroll::period');
$router->get('/api/payroll/employee/{id}', 'Payroll::employee');
$router->post('/api/payroll/generate', 'Payroll::generate');
$router->post('/api/payroll/export-pdf', 'Payroll::export_pdf');
$router->match('/api/payroll/{id}', 'Payroll::update', ['PUT']);
$router->match('/api/payroll/{id}', 'Payroll::delete', ['DELETE']);

// Payroll Periods API
$router->get('/api/payroll/periods', 'Payroll::periods');
$router->post('/api/payroll/periods', 'Payroll::create_period');
// POST fallback routes with id in URL (for update/delete/status)
$router->post('/api/payroll/periods/{id}', 'Payroll::update_period');
$router->post('/api/payroll/periods/{id}/delete', 'Payroll::delete_period');
$router->post('/api/payroll/periods/{id}/status', 'Payroll::update_period_status');
// Legacy/fallback POST routes without id in URL (controller should read id from body)
$router->post('/api/payroll/periods/update', 'Payroll::update_period');
$router->post('/api/payroll/periods/delete', 'Payroll::delete_period');
$router->post('/api/payroll/periods/status', 'Payroll::update_period_status');
// REST routes (PUT/DELETE)
$router->match('/api/payroll/periods/{id}', 'Payroll::update_period', ['PUT']);
$router->match('/api/payroll/periods/{id}', 'Payroll::delete_period', ['DELETE']);
$router->match('/api/payroll/periods/{id}/status', 'Payroll::update_period_status', ['PUT']);

// Salary Grades API
$router->get('/api/payroll/salary-grades', 'Payroll::salary_grades');
$router->post('/api/payroll/salary-grades', 'Payroll::create_salary_grade');
$router->match('/api/payroll/salary-grades/:id', 'Payroll::update_salary_grade', ['PUT']);
$router->match('/api/payroll/salary-grades/:id', 'Payroll::delete_salary_grade', ['DELETE']);
$router->post('/api/payroll/salary-grades/bulk', 'Payroll::bulk_import_salary_grades');
$router->get('/api/payroll/salary-grades/export', 'Payroll::export_salary_grades');

// Deductions API - Put specific routes BEFORE generic {id} routes
$router->get('/api/deductions', 'Deductions::index');
$router->get('/api/deductions/type/{type}', 'Deductions::get_by_type');
$router->get('/api/deductions/calculate/{type}/{salary}', 'Deductions::calculate');
$router->post('/api/deductions', 'Deductions::create');
// Currency conversion API
$router->match('/api/currency/convert', 'Currency::convert', ['GET','POST']);
$router->get('/api/currency/ping', 'Currency::ping');
// Payments / Stripe disburse
$router->get('/api/payments', 'Payments::index');
$router->post('/api/payments/disburse', 'Payments::disburse');
$router->put('/api/payments/{id}', 'Payments::update');
$router->delete('/api/payments/{id}', 'Payments::delete');
// POST fallback routes with id in URL (for update/delete)
$router->post('/api/deductions/{id}', 'Deductions::update');
$router->post('/api/deductions/{id}/delete', 'Deductions::delete');
// Legacy/fallback POST routes without id in URL
$router->post('/api/deductions/update', 'Deductions::update');
$router->post('/api/deductions/delete', 'Deductions::delete');
// REST routes (PUT/DELETE)
$router->match('/api/deductions/{id}', 'Deductions::update', ['PUT']);
$router->match('/api/deductions/{id}', 'Deductions::delete', ['DELETE']);

// Tax Brackets API - Put specific routes BEFORE generic {id} routes
$router->get('/api/deductions/tax-brackets', 'Deductions::tax_brackets');
$router->post('/api/deductions/tax-brackets', 'Deductions::create_tax_bracket');
// POST fallback routes with id in URL (for update/delete)
$router->post('/api/deductions/tax-brackets/{id}', 'Deductions::update_tax_bracket');
$router->post('/api/deductions/tax-brackets/{id}/delete', 'Deductions::delete_tax_bracket');
// Legacy/fallback POST routes without id in URL
$router->post('/api/deductions/tax-brackets/update', 'Deductions::update_tax_bracket');
$router->post('/api/deductions/tax-brackets/delete', 'Deductions::delete_tax_bracket');
// REST routes (PUT/DELETE)
$router->match('/api/deductions/tax-brackets/{id}', 'Deductions::update_tax_bracket', ['PUT']);
$router->match('/api/deductions/tax-brackets/{id}', 'Deductions::delete_tax_bracket', ['DELETE']);

// Leave Management API
$router->get('/api/leaves/types', 'Leaves::types');
// Create and update leave types
$router->post('/api/leaves/types', 'Leaves::create_type');
$router->post('/api/leaves/types/{id}', 'Leaves::update_type');
// Delete leave type
$router->post('/api/leaves/types/{id}/delete', 'Leaves::delete_type');
$router->match('/api/leaves/types/{id}', 'Leaves::delete_type', ['DELETE']);
$router->get('/api/leaves/balance/{employee_id}', 'Leaves::balance');
$router->get('/api/leaves/requests/{employee_id}', 'Leaves::requests');
$router->post('/api/leaves/submit', 'Leaves::submit');
$router->post('/api/leaves/approve', 'Leaves::approve');
$router->post('/api/leaves/reject', 'Leaves::reject');
$router->post('/api/leaves/cancel', 'Leaves::cancel');
$router->get('/api/leaves/pending', 'Leaves::pending');

// Currency Exchange Rates API
$router->get('/api/exchange-rates', 'Payments::exchange_rates');

