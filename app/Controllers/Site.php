<?php
namespace App\Controllers;
use App\Models\ProductModel;
use App\Models\ServiceModel;
use App\Models\QuoteRequestModel;
class Site extends BaseController
{
    public function index()
    {
        return view('site/home', [
            'products' => (new ProductModel())->where('is_active', 1)->limit(3)->findAll(),
            'services' => (new ServiceModel())->where('is_active', 1)->limit(7)->findAll(),
            'testimonials' => db_connect()->table('testimonials')->where('is_approved', 1)->limit(3)->get()->getResultArray(),
            'faqs' => db_connect()->table('faqs')->where('is_published', 1)->orderBy('display_order','ASC')->limit(5)->get()->getResultArray(),
        ]);
    }
    public function products()
    {
        $products = db_connect()->table('products p')
            ->select('p.*, c.category_name')->join('product_categories c', 'c.category_id = p.category_id')
            ->where('p.is_active', 1)->orderBy('p.product_name')->get()->getResultArray();
        return view('site/products', ['products' => $products]);
    }
    public function services()
    {
        return view('site/services', ['services' => (new ServiceModel())->where('is_active', 1)->findAll()]);
    }
    public function quote()
    {
        return view('site/quote', ['services' => (new ServiceModel())->where('is_active', 1)->findAll()]);
    }
    public function submitQuote()
    {
        $rules = [
            'contact_name' => 'required|max_length[200]',
            'contact_email' => 'required|valid_email|max_length[255]',
            'contact_phone' => 'permit_empty|max_length[30]',
            'details' => 'required|max_length[5000]',
            'service_id' => 'permit_empty|is_natural_no_zero',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $serviceId = $this->request->getPost('service_id');
        if ($serviceId && ! (new ServiceModel())->where('is_active', 1)->find((int)$serviceId)) {
            return redirect()->back()->withInput()->with('error','Invalid service selected.');
        }
        (new QuoteRequestModel())->insert([
            'customer_id' => session('customer_id') ?: null,
            'service_id' => $serviceId ? (int)$serviceId : null,
            'contact_name' => trim((string)$this->request->getPost('contact_name')),
            'contact_email' => trim((string)$this->request->getPost('contact_email')),
            'contact_phone' => trim((string)$this->request->getPost('contact_phone')),
            'details' => trim((string)$this->request->getPost('details')),
        ]);
        return redirect()->to(site_url('quote'))->with('success','Your quote request was sent.');
    }
}
