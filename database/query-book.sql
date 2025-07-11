--SELECT * FROM public.orders where delivery_status != 'POD' ORDER BY id ASC 

SELECT id,delivery_status FROM public.orders where tracking_number = 5100308838

-- select count(*) from orders where delivery_status = 'POD' AND DATE(created_at) = '2025-07-11'
--select count(*) from orders where DATE(created_at) < '2025-07-11'

-- update orders set delivery_status = 'POD'  where DATE(created_at) < '2025-07-11'

select 
	customers.first_name,orders.* as full_name 
	from orders join customers on orders.customer_id = customers.id  
	where   DATE(orders.created_at) >= '2025-07-10'



-- update orders set delivery_service_id = 2  WHERE delivery_status = 'POD'

-- select * from orders where id = 327
