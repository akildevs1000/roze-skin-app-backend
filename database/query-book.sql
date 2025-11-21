--SELECT * FROM public.orders where delivery_status != 'POD' ORDER BY id ASC 



-- select count(*) from orders where delivery_status = 'POD' AND DATE(created_at) = '2025-07-11'
--select count(*) from orders where DATE(created_at) < '2025-07-11'

-- update orders set delivery_status = 'POD'  where DATE(created_at) < '2025-07-11'

-- select 
-- 	customers.first_name,orders.* as full_name 
-- 	from orders join customers on orders.customer_id = customers.id  
-- 	where orders.tracking_number = 5100308838

-- update orders set delivery_service_id = 2  WHERE delivery_status = 'POD'

-- select * from orders where id = 327


-- SELECT * FROM public.orders order by id desc limit 4
-- SELECT * FROM public.business_sources limit 10

-- select * from shipping_addresses where customer_id in (732,733,734,582)

-- select * from shipping_addresses where city = 'Dubai'


-- update public.shipping_addresses set city= 'DXB' where city in ('Mardif','Jebel Al','Deira','dubai','Burdubai','Dubai, Alquasis','Barsha 2','Al manara','DXB','Jvc','Dubai')
-- update public.shipping_addresses set city = 'AJM' where city in ('Ajman','AJM')
-- update public.shipping_addresses set city = 'ALN' where city in ('ALN','Al Ain')
-- update public.shipping_addresses set city = 'RAK' where city in ('RAK','Ras Al Khaimah')
-- update public.shipping_addresses set city = 'SHJ' where city in ('Kalba','Sharjah','SHJ')
-- update public.shipping_addresses set city = 'AUH' where city in ('Abu Dhabi','Abudabi','Abudhabi','AUH')
-- update public.shipping_addresses set city = 'FUJ' where city in ('Fujairah','FUJ')
-- update public.shipping_addresses set city = 'UAQ' where city in ('Umm Al Quwain','UAQ')


